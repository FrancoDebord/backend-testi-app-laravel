<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\NotificationType;
use App\Enums\UserAccountStatus;
use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\User;
use App\Support\NotificationText;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Mentions « @ » dans les commentaires (témoignages, directs, messages des requêtes de prière),
 * le texte des témoignages (notifyTestimony, à l'approbation) et des requêtes de prière (notifyPrayerRequest).
 *
 * Format enregistré (stable, indépendant des changements de nom) : `@[Nom affiché](user:uuid)`.
 * Les API et le site n'exposent jamais ce format brut : le texte est rendu « @Nom affiché »
 * et la liste `mentions: [{id, displayName}]` permet de rendre les mentions cliquables.
 *
 * À l'enregistrement, prepare() :
 *  - garde les jetons qui désignent un compte actif (nom remplacé par son nom actuel : pas d'usurpation) ;
 *  - transforme les autres en texte simple ;
 *  - reconnaît aussi « @Nom » pour les comptes indiqués (`mention_ids`) ou déjà mentionnés (modification).
 * Voir docs/fonctionnalites/mentions.md
 */
class Mentions
{
    public const TOKEN = '/@\[([^\[\]\r\n]{1,100})\]\(user:([0-9a-fA-F-]{36})\)/u';

    /** Mentions retenues par commentaire (les suivantes restent du texte). */
    public const MAX_PER_TEXT = 10;

    public const SEARCH_LIMIT = 8;

    // ─── Lecture ─────────────────────────────────────────────────────────────

    /** Texte affichable : `@[Nom](user:id)` → `@Nom`. */
    public static function plain(?string $text): string
    {
        return preg_replace(self::TOKEN, '@$1', (string) $text) ?? (string) $text;
    }

    /** @return array<int, array{id: string, displayName: string}> mentions du texte, sans doublon. */
    public static function of(?string $text): array
    {
        if ($text === null || !str_contains($text, '](user:')) {
            return [];
        }
        preg_match_all(self::TOKEN, $text, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn ($m) => ['id' => strtolower($m[2]), 'displayName' => $m[1]])
            ->unique('id')->values()->all();
    }

    /** @return string[] identifiants mentionnés. */
    public static function ids(?string $text): array
    {
        return array_column(self::of($text), 'id');
    }

    /** Rendu HTML (site) : texte échappé, mentions en liens vers le profil. */
    public static function html(?string $text): HtmlString
    {
        $text = (string) $text;
        $html = '';
        $last = 0;
        preg_match_all(self::TOKEN, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $m) {
            [$full, $offset] = $m[0];
            $html .= e(substr($text, $last, $offset - $last));
            $html .= '<a href="' . e(route('profiles.show', strtolower($m[2][0]))) . '" class="font-semibold text-primary-700 hover:underline" data-mention-link="' . e(strtolower($m[2][0])) . '">@' . e($m[1][0]) . '</a>';
            $last = $offset + strlen($full);
        }
        $html .= e(substr($text, $last));

        return new HtmlString($html);
    }

    // ─── Écriture ────────────────────────────────────────────────────────────

    /**
     * Normalise les mentions d'un texte avant enregistrement.
     *
     * @param string[]     $hintIds  comptes choisis dans l'autocomplétion (« @Nom » en clair)
     * @param string|null  $previous texte enregistré avant modification (ses mentions restent reconnues)
     * @return array{text: string, ids: string[]}
     */
    public static function prepare(string $text, array $hintIds = [], ?string $previous = null): array
    {
        $tokenIds = [];
        preg_match_all(self::TOKEN, $text, $found, PREG_SET_ORDER);
        foreach ($found as $m) {
            $tokenIds[] = strtolower($m[2]);
        }
        $plainIds = array_merge(array_map('strtolower', array_filter($hintIds, fn ($id) => is_string($id) && Str::isUuid($id))), self::ids($previous));
        $all = array_values(array_unique(array_merge($tokenIds, $plainIds)));
        if ($all === []) {
            return ['text' => $text, 'ids' => []];
        }

        $users = User::query()
            ->whereIn('id', array_slice($all, 0, 50))
            ->where('status', UserAccountStatus::Active->value)
            ->get(['id', 'display_name'])
            ->keyBy(fn (User $u) => strtolower($u->id));

        $kept = [];
        // 1. Jetons : compte actif → nom actuel ; sinon texte simple.
        $text = preg_replace_callback(self::TOKEN, function ($m) use ($users, &$kept) {
            $id = strtolower($m[2]);
            $user = $users[$id] ?? null;
            if (!$user || (!isset($kept[$id]) && count($kept) >= self::MAX_PER_TEXT)) {
                return '@' . $m[1];
            }
            $kept[$id] = true;

            return self::token($user);
        }, $text) ?? $text;

        // 2. « @Nom » en clair pour les comptes indiqués ou déjà mentionnés (noms les plus longs d'abord).
        $plain = collect($plainIds)->unique()->map(fn ($id) => $users[$id] ?? null)->filter()
            ->sortByDesc(fn (User $u) => mb_strlen($u->display_name));
        foreach ($plain as $user) {
            $id = strtolower($user->id);
            if (!isset($kept[$id]) && count($kept) >= self::MAX_PER_TEXT) {
                continue;
            }
            $name = trim((string) $user->display_name);
            if ($name === '') continue;
            // « @Nom » non suivi d'une lettre ou d'un chiffre ; jamais à l'intérieur d'un jeton.
            $pattern = '/(?<![\p{L}\p{N}_])@' . preg_quote($name, '/') . '(?![\p{L}\p{N}_])/u';
            $token = self::token($user);
            $text = self::outsideTokens($text, fn (string $segment) => preg_replace_callback($pattern, fn () => $token, $segment) ?? $segment);
            if (str_contains($text, $token)) {
                $kept[$id] = true;
            }
        }

        return ['text' => $text, 'ids' => array_keys($kept)];
    }

    /**
     * Prévient les personnes mentionnées (notification « mention » + push, préférence « commentaires »).
     * Jamais l'auteur, ni les comptes déjà prévenus par une autre notification ($skip). Jamais bloquant.
     *
     * @param string[] $ids
     * @param string[] $skip
     * `anonymous` : l'auteur n'est pas révélé (« Quelqu'un vous a mentionné… », ni identifiant ni photo).
     *
     * Lieu de la mention : `where_key` (clé de inbox.notify_mention : comment, testimony, prayer_request,
     * verse_comment, event_comment, live, prayer_message) et `where_params` (ex. ['title' => …]), traduits
     * dans la langue du destinataire ; `where` (texte français libre) reste accepté.
     *
     * @param array{testimony_id?: ?string, testimony_title?: ?string, comment_id?: ?string, payload?: array, where?: string, where_key?: string, where_params?: array, anonymous?: bool} $context
     */
    public static function notify(array $ids, User $actor, array $context = [], array $skip = []): int
    {
        $skip = array_map('strtolower', [...$skip, $actor->id]);
        $targets = array_values(array_diff(array_unique(array_map('strtolower', $ids)), $skip));
        if ($targets === []) {
            return 0;
        }

        $anonymous = (bool) ($context['anonymous'] ?? false);
        $name = $anonymous ? __('inbox.someone', [], \App\Support\Locales::DEFAULT) : $actor->display_name;
        // Texte traduisible (clé + paramètres), sauf lieu donné en texte libre (`where`).
        $text = isset($context['where']) && !isset($context['where_key'])
            ? ['message' => $name . ' vous a mentionné dans ' . $context['where'], 'payload' => $context['payload'] ?? null]
            : NotificationText::fields(
                'inbox.' . ($anonymous ? 'notify_mention_anonymous' : 'notify_mention') . '.' . ($context['where_key'] ?? 'comment'),
                ($anonymous ? [] : ['name' => $name]) + ($context['where_params'] ?? []),
                $context['payload'] ?? [],
            );
        $sent = 0;
        foreach (User::whereIn('id', $targets)->where('status', UserAccountStatus::Active->value)->pluck('id') as $recipientId) {
            try {
                AppNotification::create([
                    'recipient_id'    => $recipientId,
                    'actor_id'        => $anonymous ? null : $actor->id,
                    'actor_name'      => $name,
                    'actor_avatar'    => $anonymous ? null : $actor->avatar_url,
                    'type'            => NotificationType::Mention->value,
                    'testimony_id'    => $context['testimony_id'] ?? null,
                    'testimony_title' => $context['testimony_title'] ?? null,
                    'comment_id'      => $context['comment_id'] ?? null,
                    'message'         => $text['message'],
                    'payload'         => $text['payload'],
                    'created_at'      => now(),
                ]);
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    /**
     * Mentions du texte d'un témoignage, prévenues quand il devient visible (approbation par la modération).
     * Une seule fois par personne et par témoignage (notification « mention » déjà envoyée = pas de nouvelle,
     * même après modification puis nouvelle approbation) ; seulement les personnes qui peuvent le lire
     * (public : tous ; abonnés : les abonnés de l'auteur). Jamais pour le carnet privé ni un témoignage non approuvé.
     */
    public static function notifyTestimony(\App\Models\Testimony $testimony): int
    {
        $author = $testimony->user;
        if (!$author || $testimony->isInJournal() || $testimony->status !== \App\Enums\TestimonyStatus::Approved) {
            return 0;
        }
        $ids = self::ids($testimony->body_text);
        if ($ids === []) {
            return 0;
        }

        $already = AppNotification::where('type', NotificationType::Mention->value)
            ->where('testimony_id', $testimony->id)->whereNull('comment_id')
            ->where('payload->mention_in', 'testimony')
            ->whereIn('recipient_id', $ids)->pluck('recipient_id')->map(fn ($id) => strtolower($id))->all();
        $visible = User::whereIn('id', array_diff($ids, $already))->get()
            ->filter(fn (User $u) => $testimony->isVisibleTo($u))->pluck('id')->all();

        return self::notify($visible, $author, [
            'testimony_id'    => $testimony->id,
            'testimony_title' => $testimony->title,
            'where_key'       => 'testimony',
            'where_params'    => ['title' => $testimony->title],
            'payload'         => ['mention_in' => 'testimony'],
        ]);
    }

    /**
     * Mentions du texte d'une requête de prière (création, modification) : seules les personnes qui peuvent
     * la voir, une seule fois par personne ; jamais pour une requête privée ou retirée par la modération.
     * Requête anonyme : « Quelqu'un vous a mentionné dans une requête de prière » (auteur non révélé).
     */
    public static function notifyPrayerRequest(\App\Models\PrayerRequest $request): int
    {
        $author = $request->user;
        if (!$author || $request->visibility === \App\Models\PrayerRequest::PRIVATE || $request->isHidden()) {
            return 0;
        }
        $ids = self::ids($request->body);
        if ($ids === []) {
            return 0;
        }

        $already = AppNotification::where('type', NotificationType::Mention->value)
            ->where('payload->mention_in', 'prayer_request')
            ->where('payload->prayer_request_id', $request->id)
            ->whereIn('recipient_id', $ids)->pluck('recipient_id')->map(fn ($id) => strtolower($id))->all();
        $visible = User::whereIn('id', array_diff($ids, $already))->get()
            ->filter(fn (User $u) => $request->isVisibleTo($u))->pluck('id')->all();

        return self::notify($visible, $author, [
            'where_key' => 'prayer_request',
            'anonymous' => (bool) $request->is_anonymous,
            'payload'   => ['prayer_request_id' => $request->id, 'mention_in' => 'prayer_request'],
        ]);
    }

    // ─── Recherche (autocomplétion) ──────────────────────────────────────────

    /**
     * Comptes actifs à proposer après « @ » : nom affiché ou nom d'organisation.
     * Priorité : comptes suivis, puis personnes ayant participé au témoignage (auteur, commentaires),
     * puis début de nom, puis organisations vérifiées. Jamais d'e-mail ni de téléphone.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function search(User $viewer, string $q, int $limit = self::SEARCH_LIMIT, ?string $testimonyId = null): Collection
    {
        $q = trim(ltrim(trim($q), '@'));
        $q = mb_substr($q, 0, 60);
        $limit = max(1, min(20, $limit));

        $followed = \Illuminate\Support\Facades\DB::table('follows')->where('follower_id', $viewer->id)->limit(1000)->pluck('following_id')
            ->map(fn ($id) => strtolower($id))->flip();
        $participants = collect();
        if ($testimonyId && Str::isUuid($testimonyId)) {
            $participants = Comment::where('testimony_id', $testimonyId)->latest()->limit(200)->pluck('user_id')
                ->push(\App\Models\Testimony::whereKey($testimonyId)->value('user_id'))
                ->filter()->map(fn ($id) => strtolower($id))->flip();
        }

        $query = User::query()
            ->where('status', UserAccountStatus::Active->value)
            ->whereKeyNot($viewer->id);

        if ($q === '') {
            // « @ » seul : comptes suivis et participants de la discussion.
            $ids = array_slice(array_unique([...$followed->keys()->all(), ...$participants->keys()->all()]), 0, 200);
            if ($ids === []) {
                return collect();
            }
            $query->whereIn('id', $ids);
        } else {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(fn ($w) => $w->where('display_name', 'like', $like)->orWhere('organization_name', 'like', $like));
        }

        $needle = mb_strtolower($q);
        return $query->orderBy('display_name')->limit(60)
            ->get(['id', 'display_name', 'avatar_url', 'account_type', 'organization_name', 'verification_status'])
            ->map(function (User $u) use ($followed, $participants, $needle) {
                $id = strtolower($u->id);
                $name = mb_strtolower((string) $u->display_name);
                $score = 0;
                if ($followed->has($id)) $score += 8;
                if ($participants->has($id)) $score += 4;
                if ($needle !== '' && (str_starts_with($name, $needle) || str_contains($name, ' ' . $needle))) $score += 2;
                if ($u->isVerified()) $score += 1;

                return ['user' => $u, 'score' => $score, 'following' => $followed->has($id)];
            })
            ->sortBy([['score', 'desc'], [fn ($r) => mb_strtolower($r['user']->display_name), 'asc']])
            ->take($limit)
            ->map(fn ($r) => [
                'id'             => $r['user']->id,
                'displayName'    => $r['user']->display_name,
                'avatarUrl'      => $r['user']->avatar_url,
                'initials'       => $r['user']->initials,
                'isOrganization' => $r['user']->account_type === AccountType::Organization,
                'isVerified'     => $r['user']->isVerified(),
                ...\App\Models\User::approvalFieldsFor($r['user']), // badge-approuve.md
                'isFollowing'    => $r['following'],
            ])
            ->values();
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    private static function token(User $user): string
    {
        // Crochets et sauts de ligne retirés du nom : le jeton reste lisible par l'expression.
        $name = trim(preg_replace('/[\[\]\r\n]+/u', ' ', (string) $user->display_name));

        return '@[' . mb_substr($name, 0, 100) . '](user:' . $user->id . ')';
    }

    /** Applique $fn aux seuls passages hors jetons. */
    private static function outsideTokens(string $text, \Closure $fn): string
    {
        $parts = preg_split('/(@\[[^\[\]\r\n]{1,100}\]\(user:[0-9a-fA-F-]{36}\))/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = $fn($part);
            }
        }

        return implode('', $parts);
    }
}

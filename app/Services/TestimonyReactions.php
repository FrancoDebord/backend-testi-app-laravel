<?php

namespace App\Services;

use App\Enums\ReactionType;
use App\Models\AppNotification;
use App\Models\Reaction;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Réactions aux témoignages (API et site) : chaque personne a au plus UNE réaction
 * « j'aime » (like, love, fire → like_count) et UNE réaction « je prie » (pray, amen,
 * worship → prayer_count) par témoignage. Réagir de nouveau ne compte jamais deux fois ;
 * changer de réaction remplace l'ancienne du même groupe. Voir docs/fonctionnalites/reactions.md
 */
class TestimonyReactions
{
    /**
     * Pose la réaction `$type` (idempotent). Retourne [réaction, nouvelle ?].
     * Une réaction différente du même groupe est remplacée sans changer le compteur.
     *
     * @return array{0: Reaction, 1: bool}
     */
    public function set(User $user, Testimony $testimony, ReactionType $type): array
    {
        [$reaction, $created] = DB::transaction(function () use ($user, $testimony, $type) {
            Testimony::whereKey($testimony->id)->lockForUpdate()->first();

            $mine = Reaction::where('user_id', $user->id)->where('testimony_id', $testimony->id)
                ->whereIn('type', ReactionType::valuesOfGroup($type->group()))
                ->get();

            $same = $mine->first(fn (Reaction $r) => $r->type === $type);
            $others = $mine->reject(fn (Reaction $r) => $r->type === $type);

            // Nouvelle prière (aucune réaction du groupe « je prie ») : le temps de prière doit être écoulé.
            // Passer de pray à amen, ou prier de nouveau, n'est jamais bloqué (docs/fonctionnalites/temps-de-priere.md).
            if ($type->group() === 'prayer' && $mine->isEmpty()) {
                app(PrayerFocus::class)->ensureCompleted($user, 'testimony', (string) $testimony->id);
            }

            if ($others->isNotEmpty()) {
                Reaction::whereKey($others->modelKeys())->delete();
                $this->decrement($testimony, $type->counterField(), $others->count());
            }
            if ($same) {
                // Doublons éventuels déjà retirés ci-dessus : rien d'autre à compter.
                return [$same, false];
            }

            $reaction = Reaction::create(['user_id' => $user->id, 'testimony_id' => $testimony->id, 'type' => $type->value]);
            Testimony::whereKey($testimony->id)->increment($type->counterField());

            // Nouvelle réaction du groupe (pas un simple changement d'émoji) : on prévient l'auteur.
            return [$reaction, $others->isEmpty()];
        });

        if ($created) $this->notifyAuthor($user, $testimony, $type);

        return [$reaction, $created];
    }

    /**
     * Retire les réactions de la personne : celles du groupe `$group` ('appreciation' | 'prayer'),
     * ou toutes si null. Retourne le nombre de réactions retirées.
     */
    public function remove(User $user, Testimony $testimony, ?string $group = null): int
    {
        return DB::transaction(function () use ($user, $testimony, $group) {
            Testimony::whereKey($testimony->id)->lockForUpdate()->first();

            $mine = Reaction::where('user_id', $user->id)->where('testimony_id', $testimony->id)
                ->when($group, fn ($q) => $q->whereIn('type', ReactionType::valuesOfGroup($group)))
                ->get();
            if ($mine->isEmpty()) return 0;

            Reaction::whereKey($mine->modelKeys())->delete();
            foreach ($mine->groupBy(fn (Reaction $r) => $r->type->counterField()) as $field => $rows) {
                $this->decrement($testimony, $field, $rows->count());
            }

            return $mine->count();
        });
    }

    /**
     * Commence (ou recommence) le temps de prière avant « Je prie » sur un témoignage que la personne
     * peut consulter. Null si le témoignage ne lui est pas visible (404).
     *
     * @return array{startedAt: string, requiredSeconds: int, confirmableAt: string}|null
     */
    public function startPrayer(User $user, Testimony $testimony): ?array
    {
        if (!$testimony->isVisibleTo($user)) {
            return null;
        }

        return app(PrayerFocus::class)->start($user, 'testimony', (string) $testimony->id);
    }

    /** Bascule `$type` : retire la réaction si c'est déjà celle-là, sinon la pose. Retourne true si posée. */
    /**
     * « Je prie », comme l'application mobile (InteractionNotifier::togglePray) : un seul bouton. La personne
     * prie déjà (pray, ou ancienne réaction amen / worship) → sa prière est retirée ; sinon « pray » est posé.
     * Renvoie vrai si la personne prie après l'appel.
     */
    public function togglePrayer(User $user, Testimony $testimony): bool
    {
        $prays = Reaction::where('user_id', $user->id)->where('testimony_id', $testimony->id)
            ->whereIn('type', ReactionType::valuesOfGroup('prayer'))->exists();
        if ($prays) {
            $this->remove($user, $testimony, 'prayer');
            return false;
        }
        $this->set($user, $testimony, ReactionType::Pray);

        return true;
    }

    public function toggle(User $user, Testimony $testimony, ReactionType $type): bool
    {
        $current = Reaction::where('user_id', $user->id)->where('testimony_id', $testimony->id)
            ->where('type', $type->value)->exists();
        if ($current) {
            $this->remove($user, $testimony, $type->group());
            return false;
        }
        $this->set($user, $testimony, $type);

        return true;
    }

    /**
     * État des réactions de la personne et compteurs à jour (réponse de l'API).
     *
     * @return array{myReaction: ?string, myPrayerReaction: ?string, isLikedByMe: bool, isPrayedByMe: bool, likesCount: int, prayersCount: int}
     */
    public function state(?User $user, Testimony $testimony): array
    {
        $fresh = Testimony::query()->select(['id', 'like_count', 'prayer_count'])->find($testimony->id);
        $types = $user
            ? Reaction::where('user_id', $user->id)->where('testimony_id', $testimony->id)->get()->map(fn ($r) => $r->type)
            : collect();
        $appreciation = $types->first(fn (ReactionType $t) => $t->group() === 'appreciation');
        $prayer = $types->first(fn (ReactionType $t) => $t->group() === 'prayer');

        return [
            'myReaction'       => $appreciation?->value,
            'myPrayerReaction' => $prayer?->value,
            'isLikedByMe'      => $appreciation !== null,
            'isPrayedByMe'     => $prayer !== null,
            'likesCount'       => (int) ($fresh?->like_count ?? 0),
            'prayersCount'     => (int) ($fresh?->prayer_count ?? 0),
        ];
    }

    private function decrement(Testimony $testimony, string $field, int $by): void
    {
        // Jamais négatif (compteurs non signés en MySQL).
        Testimony::whereKey($testimony->id)->update([$field => DB::raw("CASE WHEN {$field} > {$by} THEN {$field} - {$by} ELSE 0 END")]);
    }

    private function notifyAuthor(User $user, Testimony $testimony, ReactionType $type): void
    {
        if ($testimony->user_id === $user->id) return;

        $prayer = $type->group() === 'prayer';
        // Jamais bloquant : la réaction est déjà enregistrée, une notification en échec ne doit pas la faire échouer.
        try {
            AppNotification::create([
                'recipient_id'    => $testimony->user_id,
                'actor_id'        => $user->id,
                'actor_name'      => $user->display_name,
                'actor_avatar'    => $user->avatar_url,
                // « Je prie » : type prayer (icône et préférence push_prayers dans l'application)
                'type'            => $prayer ? 'prayer' : 'like',
                'testimony_id'    => $testimony->id,
                'testimony_title' => $testimony->title,
                ...\App\Support\NotificationText::fields($prayer ? 'reactions.notify_prayer' : 'reactions.notify_like', ['actor' => $user->display_name]),
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

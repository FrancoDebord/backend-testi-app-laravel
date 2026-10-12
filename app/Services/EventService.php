<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\NotificationType;
use App\Enums\TestimonyStatus;
use App\Services\Mentions;
use App\Enums\UserAccountStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\EventImage;
use App\Models\EventParticipation;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Règles métier des événements chrétiens, partagées par le site et l'API mobile.
 * Voir docs/fonctionnalites/evenements.md
 */
class EventService
{
    public const IMAGE_DIRECTORY = 'event-images';

    // ─── Validation ──────────────────────────────────────────────────────────

    /** Règles de création / modification (`$partial` : modification, champs facultatifs). */
    public static function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'title'            => [$req, 'string', 'max:150'],
            'description'      => ['nullable', 'string', 'max:5000'],
            'type'             => [$req, Rule::enum(EventType::class)],
            // « postponed » : accepté seulement s'il l'est déjà (le report passe par POST events/{id}/postpone).
            'status'           => ['nullable', Rule::in([EventStatus::Draft->value, EventStatus::Published->value, EventStatus::Postponed->value, EventStatus::Cancelled->value])],
            'starts_at'        => [$req, 'date'],
            'ends_at'          => [$req, 'date', 'after_or_equal:starts_at'],
            'location'         => ['nullable', 'string', 'max:200'],
            'city'             => ['nullable', 'string', 'max:100'],
            'country'          => ['nullable', 'string', 'max:100'],
            // Adresse complète et repère GPS (facultatifs ; latitude et longitude vont ensemble).
            'address'          => ['nullable', 'string', 'max:300'],
            'latitude'         => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude'        => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'comments_enabled' => ['nullable', 'boolean'],
            'guests'           => ['nullable', 'array', 'max:' . Event::MAX_GUESTS],
            'guests.*.name'    => ['required', 'string', 'max:120'],
            'guests.*.role'    => ['nullable', 'string', 'max:120'],
            // Gestionnaire d'une organisation : événement créé au nom de celle-ci.
            'organization_id'  => ['nullable', 'uuid'],
        ];
    }

    public const MESSAGES = [
        'title.required'      => "Donnez un titre à l'événement.",
        'type.required'       => "Choisissez le type d'événement.",
        'starts_at.required'  => 'Indiquez la date de début.',
        'ends_at.required'    => 'Indiquez la date de fin.',
        'ends_at.after_or_equal' => 'La fin doit être après le début.',
        'guests.max'          => 'Dix invités au plus.',
        'guests.*.name.required' => "Indiquez le nom de l'invité.",
        'address.max'            => "L'adresse ne doit pas dépasser 300 caractères.",
        'latitude.between'       => 'Latitude non valide (entre -90 et 90).',
        'longitude.between'      => 'Longitude non valide (entre -180 et 180).',
        'latitude.required_with' => 'Placez le repère sur la carte (latitude manquante).',
        'longitude.required_with' => 'Placez le repère sur la carte (longitude manquante).',
        'latitude.numeric'       => 'Latitude non valide.',
        'longitude.numeric'      => 'Longitude non valide.',
    ];

    /** Image de couverture : 8 Mo au plus, 600 × 300 px au moins. */
    public static function imageRules(): array
    {
        return ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=300'];
    }

    public const IMAGE_MESSAGES = [
        'image.required'   => 'Choisissez une image.',
        'image.image'      => "Le fichier doit être une image.",
        'image.mimes'      => "L'image doit être au format JPG, PNG ou WebP.",
        'image.max'        => "L'image ne doit pas dépasser 8 Mo.",
        'image.dimensions' => "L'image doit mesurer au moins 600 × 300 pixels.",
    ];

    // ─── Événement ───────────────────────────────────────────────────────────

    public function create(User $user, array $data): Event
    {
        $this->ensureActive($user);
        if (!Event::canBeCreatedBy($user)) {
            throw new EventActionException(
                'Seules les organisations vérifiées et les administrateurs peuvent créer un événement.', 403);
        }

        $status = $data['status'] ?? EventStatus::Published->value;
        if ($status === EventStatus::Postponed->value) { // le report passe par l'action Reporter
            $status = EventStatus::Published->value;
        }

        return Event::create([
            ...$this->attributes($data),
            'organizer_id' => $this->organizerFor($user, $data['organization_id'] ?? null)->id,
            'status'       => $status,
        ]);
    }

    /**
     * Organisateur de l'événement créé : l'organisation choisie (si la personne la gère),
     * sinon la personne elle-même (administrateur ou organisation vérifiée), sinon la seule
     * organisation vérifiée qu'elle gère.
     */
    private function organizerFor(User $user, ?string $organizationId): User
    {
        if ($organizationId && $organizationId !== $user->id) {
            $org = User::find($organizationId);
            if (!$org || !$org->isVerifiedOrganization()) {
                throw new EventActionException('Organisation introuvable ou non vérifiée.', 422);
            }
            if (!$user->isAdmin() && !$user->canActForOrganization($org)) {
                throw new EventActionException("Vous ne gérez pas cette organisation.", 403);
            }
            return $org;
        }
        if ($user->isAdmin() || $user->isVerifiedOrganization()) {
            return $user;
        }
        $orgs = $user->verifiedOrganizationsManaged()->get();
        if ($orgs->count() === 1) {
            return $orgs->first();
        }
        throw new EventActionException("Choisissez l'organisation au nom de laquelle créer l'événement.", 422);
    }

    /**
     * Modification. Passer à « Annulé » depuis le formulaire prévient les participants comme
     * l'action « Annuler » ; changer la date d'un événement « date à confirmer » le reprogramme
     * (il redevient « Publié », l'ancienne date restant affichée barrée).
     */
    public function update(Event $event, User $user, array $data): Event
    {
        $this->ensureManager($event, $user);
        $attrs        = $this->attributes($data, $event);
        $wasCancelled = $event->isCancelled();
        $wasTbd       = $event->isDateTbd();
        $rescheduled  = false;

        // Le statut « Reporté » ne s'obtient que par l'action Reporter.
        if (($attrs['status'] ?? null) === EventStatus::Postponed->value && $event->status !== EventStatus::Postponed) {
            unset($attrs['status']);
        }
        if ($wasTbd) {
            $newStart = isset($attrs['starts_at']) ? \Illuminate\Support\Carbon::parse($attrs['starts_at']) : null;
            if ($newStart && !$newStart->equalTo($event->starts_at)) {
                $rescheduled = true;
                $attrs['date_tbd'] = false;
                if (($attrs['status'] ?? EventStatus::Postponed->value) === EventStatus::Postponed->value) {
                    $attrs['status'] = EventStatus::Published->value;
                }
            } elseif (isset($attrs['status']) && $attrs['status'] !== EventStatus::Postponed->value) {
                $attrs['date_tbd'] = false;
            }
        }
        if (isset($attrs['status']) && $attrs['status'] !== ($event->status?->value)) {
            $attrs['status_changed_at'] = now();
        }

        $event->update($attrs);
        $event->refresh();

        if (!$wasCancelled && $event->isCancelled()) {
            $this->notifyGoing($event, NotificationType::EventCancelled, $this->cancelMessage($event), $user);
        } elseif ($rescheduled && $event->isOpen()) {
            $this->notifyGoing($event, NotificationType::EventPostponed, $this->postponeMessage($event), $user);
        }

        return $event;
    }

    // ─── Report et annulation ────────────────────────────────────────────────

    /** Report : nouvelle date (début et fin) ou « date à confirmer » (`date_tbd`), motif facultatif. */
    public static function postponeRules(bool $dateTbd): array
    {
        return [
            'date_tbd'  => ['nullable', 'boolean'],
            'reason'    => ['nullable', 'string', 'max:500'],
            ...($dateTbd ? [] : [
                'starts_at' => ['required', 'date', 'after:now'],
                'ends_at'   => ['required', 'date', 'after_or_equal:starts_at'],
            ]),
        ];
    }

    public const POSTPONE_MESSAGES = [
        'starts_at.required'     => 'Indiquez la nouvelle date de début, ou choisissez « Date à confirmer ».',
        'starts_at.after'        => 'La nouvelle date doit être dans le futur.',
        'starts_at.date'         => 'Date de début non valide.',
        'ends_at.required'       => 'Indiquez la nouvelle date de fin.',
        'ends_at.date'           => 'Date de fin non valide.',
        'ends_at.after_or_equal' => 'La fin doit être après le début.',
        'reason.max'             => 'Le motif ne doit pas dépasser 500 caractères.',
    ];

    /**
     * Reporte l'événement (gestionnaires). Avec nouvelle date : il redevient « à venir » (statut
     * Publié) ; sans date : statut Reporté, « date à confirmer ». La première date annoncée est
     * conservée (`original_starts_at`). Les personnes qui participent sont prévenues.
     */
    public function postpone(Event $event, User $user, ?string $startsAt, ?string $endsAt, ?string $reason = null): Event
    {
        $this->ensureManager($event, $user);
        if (!$event->isOpen()) {
            throw new EventActionException($event->isCancelled()
                ? 'Un événement annulé ne peut pas être reporté.'
                : "Publiez l'événement avant de le reporter.", 422);
        }
        if ($event->phase() === 'past') {
            throw new EventActionException('Cet événement est terminé.', 422);
        }

        $attrs = [
            'original_starts_at' => $event->original_starts_at ?? $event->starts_at,
            'original_ends_at'   => $event->original_ends_at ?? $event->ends_at,
            'postponed_at'       => now(),
            'status_changed_at'  => now(),
            'status_reason'      => filled($reason) ? trim($reason) : null,
        ];
        if ($startsAt === null) {
            $attrs += ['status' => EventStatus::Postponed->value, 'date_tbd' => true];
        } else {
            $start = \Illuminate\Support\Carbon::parse($startsAt);
            $end   = \Illuminate\Support\Carbon::parse($endsAt ?? $startsAt);
            if ($end->lt($start)) {
                throw new EventActionException('La fin doit être après le début.', 422);
            }
            $attrs += [
                'status' => EventStatus::Published->value, 'date_tbd' => false,
                'starts_at' => $start, 'ends_at' => $end, 'survey_sent_at' => null,
            ];
        }
        $event->update($attrs);
        $event->refresh();
        $this->notifyGoing($event, NotificationType::EventPostponed, $this->postponeMessage($event), $user);

        return $event;
    }

    /** Annule l'événement (gestionnaires), motif facultatif ; les personnes qui participent sont prévenues. */
    public function cancel(Event $event, User $user, ?string $reason = null): Event
    {
        $this->ensureManager($event, $user);
        if ($event->isCancelled()) {
            throw new EventActionException('Cet événement est déjà annulé.', 422);
        }
        if ($event->status === EventStatus::Draft) {
            throw new EventActionException("Un brouillon n'est pas annoncé : supprimez-le simplement.", 422);
        }
        if ($event->phase() === 'past') {
            throw new EventActionException('Cet événement est terminé.', 422);
        }
        $event->update([
            'status'            => EventStatus::Cancelled->value,
            'date_tbd'          => false,
            'status_reason'     => filled($reason) ? trim($reason) : null,
            'status_changed_at' => now(),
        ]);
        $event->refresh();
        $this->notifyGoing($event, NotificationType::EventCancelled, $this->cancelMessage($event), $user);

        return $event;
    }

    private function postponeMessage(Event $event): string
    {
        $text = $event->isDateTbd()
            ? "L'événement « {$event->title} » est reporté. La nouvelle date sera annoncée prochainement."
            : "L'événement « {$event->title} » est reporté au " . $event->starts_at->translatedFormat('l j F Y à H:i') . '.';

        return filled($event->status_reason) ? $text . ' Motif : ' . $event->status_reason : $text;
    }

    private function cancelMessage(Event $event): string
    {
        $text = "L'événement « {$event->title} » est annulé.";

        return filled($event->status_reason) ? $text . ' Motif : ' . $event->status_reason : $text;
    }

    /** Notification (et push) aux personnes qui ont répondu « Je participe ». Ne bloque jamais l'action. */
    private function notifyGoing(Event $event, NotificationType $type, string $message, ?User $actor = null): int
    {
        return EventModeration::notify(
            $event,
            $event->participations()->where('status', EventParticipation::GOING)->pluck('user_id')->all(),
            $type,
            $message,
            exclude: array_filter([$actor?->id]),
        );
    }

    public function delete(Event $event, User $user): void
    {
        $this->ensureActive($user);
        if (!$event->canBeDeletedBy($user)) {
            throw new EventActionException("Seuls l'organisateur et les administrateurs peuvent supprimer cet événement.", 403);
        }
        foreach ($event->images as $image) {
            $this->deleteFile($image->url);
        }
        $event->images()->delete();
        $event->delete();
    }

    private function attributes(array $data, ?Event $event = null): array
    {
        $out = collect($data)->only([
            'title', 'description', 'type', 'status', 'starts_at', 'ends_at',
            'location', 'city', 'country', 'comments_enabled',
            'address', 'latitude', 'longitude',
        ])->all();

        // Repère GPS : arrondi à 7 décimales (≈ 1 cm), effacé si l'un des deux manque.
        if (array_key_exists('latitude', $out) || array_key_exists('longitude', $out)) {
            $lat = $out['latitude'] ?? null;
            $lng = $out['longitude'] ?? null;
            $ok  = is_numeric($lat) && is_numeric($lng);
            $out['latitude']  = $ok ? round((float) $lat, 7) : null;
            $out['longitude'] = $ok ? round((float) $lng, 7) : null;
        }

        if (array_key_exists('guests', $data)) {
            $out['guests'] = collect($data['guests'] ?? [])
                ->map(fn ($g) => ['name' => trim($g['name'] ?? ''), 'role' => filled($g['role'] ?? null) ? trim($g['role']) : null])
                ->filter(fn ($g) => $g['name'] !== '')
                ->values()->all();
        }
        // Modification partielle : la fin ne peut pas précéder le début déjà enregistré.
        if ($event && (isset($out['starts_at']) || isset($out['ends_at']))) {
            $start = isset($out['starts_at']) ? \Illuminate\Support\Carbon::parse($out['starts_at']) : $event->starts_at;
            $end   = isset($out['ends_at']) ? \Illuminate\Support\Carbon::parse($out['ends_at']) : $event->ends_at;
            if ($end->lt($start)) {
                throw new EventActionException('La fin doit être après le début.', 422);
            }
        }

        return $out;
    }

    // ─── Images de couverture (carrousel) ────────────────────────────────────

    public function addImage(Event $event, User $user, UploadedFile $file): EventImage
    {
        $this->ensureManager($event, $user);
        if ($event->images()->count() >= Event::MAX_IMAGES) {
            throw new EventActionException('Six images au plus. Retirez-en une pour en ajouter une autre.', 422);
        }
        $path = $file->store(self::IMAGE_DIRECTORY, 'public');

        return $event->images()->create([
            'url'      => asset('storage/' . $path),
            'position' => (int) $event->images()->max('position') + 1,
        ]);
    }

    public function removeImage(Event $event, User $user, string $imageId): void
    {
        $this->ensureManager($event, $user);
        $image = $event->images()->whereKey($imageId)->first();
        if (!$image) throw new EventActionException('Image introuvable.', 404);
        $this->deleteFile($image->url);
        $image->delete();
    }

    /** Place l'image en tête du carrousel (vignette de l'événement). */
    public function makeCover(Event $event, User $user, string $imageId): void
    {
        $this->ensureManager($event, $user);
        $images = $event->images()->get();
        $first  = $images->firstWhere('id', $imageId);
        if (!$first) throw new EventActionException('Image introuvable.', 404);

        DB::transaction(function () use ($images, $first) {
            $first->update(['position' => 0]);
            $i = 1;
            foreach ($images->where('id', '!=', $first->id) as $img) {
                $img->update(['position' => $i++]);
            }
        });
    }

    private function deleteFile(?string $url): void
    {
        $marker = '/storage/' . self::IMAGE_DIRECTORY . '/';
        if (!$url || !Str::contains($url, $marker)) return;
        Storage::disk('public')->delete(self::IMAGE_DIRECTORY . '/' . basename(Str::after($url, $marker)));
    }

    // ─── Participation ───────────────────────────────────────────────────────

    /** « Je participe » / « Je ne participe pas ». */
    public function participate(Event $event, User $user, string $status): ?string
    {
        $this->ensureActive($user);
        if (!in_array($status, [EventParticipation::GOING, EventParticipation::NOT_GOING], true)) {
            throw new EventActionException('Réponse non reconnue.', 422);
        }
        if (!$event->acceptsParticipation()) {
            throw new EventActionException($event->isCancelled()
                ? 'Cet événement est annulé.'
                : 'Cet événement est terminé.', 422);
        }

        EventParticipation::updateOrCreate(['event_id' => $event->id, 'user_id' => $user->id], ['status' => $status]);
        $this->recountParticipations($event);

        return $status;
    }

    public function cancelParticipation(Event $event, User $user): void
    {
        EventParticipation::where('event_id', $event->id)->where('user_id', $user->id)->delete();
        $this->recountParticipations($event);
    }

    private function recountParticipations(Event $event): void
    {
        $event->update([
            'going_count'     => $event->participations()->where('status', EventParticipation::GOING)->count(),
            'not_going_count' => $event->participations()->where('status', EventParticipation::NOT_GOING)->count(),
        ]);
    }

    // ─── Commentaires (témoignages des participants) ─────────────────────────

    public function comment(Event $event, User $user, string $body): EventComment
    {
        $this->ensureActive($user);
        if (!$event->comments_enabled && !$event->canBeManagedBy($user)) {
            throw new EventActionException('Les commentaires sont fermés pour cet événement.', 403);
        }
        if ($event->status === EventStatus::Draft) {
            throw new EventActionException("Publiez l'événement avant de recevoir des commentaires.", 422);
        }
        $this->throttle("event-comment:{$event->id}:{$user->id}", 5, 60,
            'Vous commentez trop vite. Patientez un instant.');

        // Mentions « @ » (docs/fonctionnalites/mentions.md) : jetons validés avant l'enregistrement.
        $mentions = Mentions::prepare(trim($body));
        $comment  = $event->comments()->create(['user_id' => $user->id, 'body' => $mentions['text']]);
        $event->increment('comment_count');

        if ($mentions['ids'] !== []) {
            Mentions::notify($mentions['ids'], $user, [
                'where'   => "un commentaire de l'événement « {$event->title} »",
                'payload' => ['event_id' => $event->id],
            ]);
        }

        return $comment->load('user');
    }

    /** Auteur ou gestionnaire de l'événement. */
    public function deleteComment(EventComment $comment, User $user): void
    {
        $event = $comment->event;
        // Auteur, gestionnaires de l'événement, administrateurs ; jamais un modérateur.
        if ($comment->user_id !== $user->id && !$event->canBeManagedBy($user) && !$user->isAdmin()) {
            throw new EventActionException('Vous ne pouvez pas supprimer ce commentaire.', 403);
        }
        $comment->delete();
        $event->decrement('comment_count');
    }

    // ─── Témoignages officiels ───────────────────────────────────────────────

    /**
     * Enregistre un commentaire comme témoignage officiel de l'événement. L'auteur du
     * témoignage reste l'auteur du commentaire. Publié directement si un administrateur ou
     * un modérateur l'enregistre ; sinon soumis à la modération comme tout témoignage.
     */
    public function promote(EventComment $comment, User $actor, array $data = []): Testimony
    {
        $event = $comment->event;
        $this->ensureManager($event, $actor);
        if ($comment->testimony_id && $comment->testimony) {
            throw new EventActionException('Ce commentaire est déjà enregistré comme témoignage.', 409);
        }

        $slug     = $data['category'] ?? 'autre';
        $category = Category::where('slug', $slug)->first();
        $approved = $actor->canModerate();
        // Texte lisible (« @Nom ») : les jetons de mention ne passent pas dans le témoignage.
        $body     = trim($data['body_text'] ?? '') ?: Mentions::plain($comment->body);

        $testimony = DB::transaction(function () use ($comment, $event, $actor, $data, $slug, $category, $approved, $body) {
            $testimony = Testimony::create([
                'user_id'       => $comment->user_id,
                'category_id'   => $category?->id,
                'category_slug' => $category ? $slug : 'autre',
                'event_id'      => $event->id,
                'title'         => trim($data['title'] ?? '') ?: Str::limit($event->title . ' : ' . Str::of($body)->squish(), 80, '…'),
                'type'          => 'text',
                'body_text'     => $body,
                'visibility'    => 'public',
                'status'        => ($approved ? TestimonyStatus::Approved : TestimonyStatus::Pending)->value,
                'approved_by'   => $approved ? $actor->id : null,
                'approved_at'   => $approved ? now() : null,
            ]);
            $comment->update(['testimony_id' => $testimony->id]);
            $category?->increment('testimony_count');

            return $testimony;
        });

        return $testimony->load('user');
    }

    /** Un témoignage publié peut être rattaché à l'événement par un gestionnaire. */
    public function ensureCanAttach(?string $eventId, User $user): ?Event
    {
        if (!$eventId) return null;
        $event = Event::find($eventId);
        if (!$event) throw new EventActionException('Événement introuvable.', 404);
        $this->ensureManager($event, $user);

        return $event;
    }

    // ─── Co-gestionnaires d'un événement (2 au plus) ─────────────────────────

    public function addManager(Event $event, User $actor, string $userId): User
    {
        $this->ensureAdministrator($event, $actor);
        $target = $this->activeUser($userId);
        if ($target->id === $event->organizer_id) {
            throw new EventActionException("L'organisateur gère déjà l'événement.", 422);
        }
        if ($event->managers()->whereKey($target->id)->exists()) {
            throw new EventActionException('Cette personne est déjà co-gestionnaire.', 409);
        }
        if ($event->managers()->count() >= Event::MAX_MANAGERS) {
            throw new EventActionException('Deux co-gestionnaires au plus par événement. Retirez-en un pour en ajouter un autre.', 422);
        }
        $event->managers()->attach($target->id);

        return $target;
    }

    /** Par un responsable de l'événement, ou par le co-gestionnaire lui-même (se retirer). */
    public function removeManager(Event $event, User $actor, string $userId): void
    {
        if ($actor->id !== $userId) {
            $this->ensureAdministrator($event, $actor);
        }
        $event->managers()->detach($userId);
    }

    // ─── Administrateurs du compte (2 au plus, tous comptes) ─────────────────

    /** Administrateurs de compte (tous comptes) ─ docs/fonctionnalites/administrateurs-de-compte.md */
    public function addOrganizationManager(User $organization, User $actor, string $userId): User
    {
        return AccountDelegation::add($organization, $actor, $userId);
    }

    /** Par le titulaire (ou un administrateur), ou par la personne elle-même. */
    public function removeOrganizationManager(User $organization, User $actor, string $userId): void
    {
        AccountDelegation::remove($organization, $actor, $userId);
    }

    private function activeUser(string $userId): User
    {
        $user = User::find($userId);
        if (!$user || $user->status !== UserAccountStatus::Active) {
            throw new EventActionException('Compte introuvable ou inactif.', 404);
        }

        return $user;
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    /** Organisateur, gestionnaire de l'organisation ou administrateur (pas les co-gestionnaires). */
    public function ensureAdministrator(Event $event, User $user): void
    {
        $this->ensureActive($user);
        if (!$event->canBeAdministeredBy($user)) {
            throw new EventActionException("Seuls l'organisateur et les administrateurs peuvent faire cette action.", 403);
        }
    }

    public function ensureManager(Event $event, User $user): void
    {
        $this->ensureActive($user);
        if (!$event->canBeManagedBy($user)) {
            throw new EventActionException("Seuls l'organisateur, ses gestionnaires, les co-gestionnaires et les administrateurs peuvent gérer cet événement.", 403);
        }
    }

    private function ensureActive(User $user): void
    {
        if ($user->status !== UserAccountStatus::Active) {
            throw new EventActionException('Votre compte ne permet pas cette action.', 403);
        }
    }

    private function throttle(string $key, int $max, int $seconds, string $message): void
    {
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, $max)) {
            throw new EventActionException($message, 429);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, $seconds);
    }
}

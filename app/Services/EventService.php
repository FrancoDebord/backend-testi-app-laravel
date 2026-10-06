<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\TestimonyStatus;
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
            'status'           => ['nullable', Rule::in([EventStatus::Draft->value, EventStatus::Published->value, EventStatus::Cancelled->value])],
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

        return Event::create([
            ...$this->attributes($data),
            'organizer_id' => $this->organizerFor($user, $data['organization_id'] ?? null)->id,
            'status'       => $data['status'] ?? EventStatus::Published->value,
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
            if (!$org || !$org->isVerified()) {
                throw new EventActionException('Organisation introuvable ou non vérifiée.', 422);
            }
            if (!$user->isAdmin() && !$user->canActForOrganization($org)) {
                throw new EventActionException("Vous ne gérez pas cette organisation.", 403);
            }
            return $org;
        }
        if ($user->isAdmin() || $user->isVerified()) {
            return $user;
        }
        $orgs = $user->verifiedOrganizationsManaged()->get();
        if ($orgs->count() === 1) {
            return $orgs->first();
        }
        throw new EventActionException("Choisissez l'organisation au nom de laquelle créer l'événement.", 422);
    }

    public function update(Event $event, User $user, array $data): Event
    {
        $this->ensureManager($event, $user);
        $event->update($this->attributes($data, $event));

        return $event->refresh();
    }

    public function delete(Event $event, User $user): void
    {
        $this->ensureAdministrator($event, $user);
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

        $comment = $event->comments()->create(['user_id' => $user->id, 'body' => trim($body)]);
        $event->increment('comment_count');

        return $comment->load('user');
    }

    /** Auteur ou gestionnaire de l'événement. */
    public function deleteComment(EventComment $comment, User $user): void
    {
        $event = $comment->event;
        if ($comment->user_id !== $user->id && !$event->canBeManagedBy($user) && !$user->canModerate()) {
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
        $body     = trim($data['body_text'] ?? '') ?: $comment->body;

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

    // ─── Gestionnaires d'une organisation (2 au plus) ────────────────────────

    /** L'organisation (ou un administrateur) désigne une personne qui gère ses événements. */
    public function addOrganizationManager(User $organization, User $actor, string $userId): User
    {
        $this->ensureCanDelegate($organization, $actor);
        $target = $this->activeUser($userId);
        if ($target->id === $organization->id) {
            throw new EventActionException('Une organisation ne peut pas être sa propre gestionnaire.', 422);
        }
        if ($target->isOrganization()) {
            throw new EventActionException('Choisissez le compte personnel du gestionnaire, pas un compte organisation.', 422);
        }
        if ($organization->organizationManagers()->whereKey($target->id)->exists()) {
            throw new EventActionException('Cette personne est déjà gestionnaire.', 409);
        }
        if ($organization->organizationManagers()->count() >= Event::MAX_ORGANIZATION_MANAGERS) {
            throw new EventActionException('Deux gestionnaires au plus par organisation. Retirez-en un pour en ajouter un autre.', 422);
        }
        $organization->organizationManagers()->attach($target->id);

        return $target;
    }

    /** Par l'organisation (ou un administrateur), ou par le gestionnaire lui-même. */
    public function removeOrganizationManager(User $organization, User $actor, string $userId): void
    {
        if ($actor->id !== $userId) {
            $this->ensureCanDelegate($organization, $actor);
        }
        $organization->organizationManagers()->detach($userId);
    }

    private function ensureCanDelegate(User $organization, User $actor): void
    {
        $this->ensureActive($actor);
        if (!$organization->isOrganization()) {
            throw new EventActionException('Seuls les comptes organisation ont des gestionnaires.', 422);
        }
        if ($actor->id !== $organization->id && !$actor->isAdmin()) {
            throw new EventActionException("Seule l'organisation peut choisir ses gestionnaires.", 403);
        }
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

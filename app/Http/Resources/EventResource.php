<?php

namespace App\Http\Resources;

use App\Enums\LiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Événement chrétien, au format de l'API mobile. Voir docs/fonctionnalites/evenements.md
 *
 * Charger `organizer`, `images` et `lives` (directs actifs) pour éviter les requêtes en boucle.
 */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user() ?? $request->user('sanctum');
        $live = $this->relationLoaded('lives')
            ? $this->lives->first(fn ($l) => in_array($l->status, [LiveStatus::Live, LiveStatus::Preparing], true))
            : $this->activeLive();
        // Un direct en préparation n'est montré qu'aux gestionnaires.
        if ($live && $live->status === LiveStatus::Preparing && !$this->canBeManagedBy($user)) {
            $live = null;
        }
        // Détail (routes events/{id}…) : sondage personnel, signalement, fiabilité, modération.
        // Les listes s'en passent pour éviter des requêtes par événement.
        $detail    = filled($request->route('id'));
        $manager   = $this->canBeManagedBy($user);
        $moderator = (bool) $user?->canModerate();
        $moderation = app(\App\Services\EventModeration::class);
        $myReview  = $detail ? $moderation->reviewOf($this->resource, $user) : null;
        $reliability = $detail ? \App\Models\Event::reliabilityOf($this->organizer_id) : null;
        $reported  = $detail && $moderation->hasReported($this->resource, $user);

        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'type'            => $this->type->value,
            'typeLabel'       => $this->type->label(),
            'status'          => $this->status->value,
            'statusLabel'     => $this->status->label(),
            'phase'           => $this->phase(),          // upcoming | ongoing | past
            'phaseLabel'      => $this->phaseLabel(),
            'startsAt'        => $this->starts_at?->toIso8601String(),
            'endsAt'          => $this->ends_at?->toIso8601String(),
            // Report / annulation : ancienne date (affichée barrée), « date à confirmer », motif.
            'isPostponed'      => $this->isPostponed(),
            'dateTbd'          => $this->isDateTbd(),
            'originalStartsAt' => $this->isPostponed() ? $this->original_starts_at?->toIso8601String() : null,
            'originalEndsAt'   => $this->isPostponed() ? $this->original_ends_at?->toIso8601String() : null,
            'statusReason'     => $this->status_reason,
            'statusChangedAt'  => $this->status_changed_at?->toIso8601String(),
            // Modération : masqué (visible des seuls gestionnaires et modérateurs), non confirmé.
            'isHidden'         => $this->isHidden(),
            'hiddenReason'     => ($manager || $moderator) ? $this->hidden_reason : null,
            'isUnconfirmed'    => $this->isUnconfirmed(),
            'canReport'        => $user !== null && !$manager && $this->status->value !== 'draft' && !$reported,
            'hasReported'      => $reported,
            // Sondage d'après l'événement (« A-t-il vraiment eu lieu ? »).
            'survey' => [
                'open'             => $this->acceptsReviews(),
                'canAnswer'        => $detail && $moderation->canReview($this->resource, $user, $myReview),
                'yes'              => $this->survey_yes_count,
                'no'               => $this->survey_no_count,
                'absent'           => $this->survey_absent_count,
                'total'            => $this->surveyTotal(),
                'confirmedPercent' => $this->confirmedPercent(),
                'ratingAverage'    => $this->ratingAverage(),
                'ratingCount'      => $this->rating_count,
                'mine'             => $myReview ? [
                    'attended'      => $myReview->attended,
                    'rating'        => $myReview->rating,
                    'body'          => $myReview->body,
                    'editableUntil' => $myReview->editableUntil()->toIso8601String(),
                    'canEdit'       => $myReview->isEditable() && $this->acceptsReviews(),
                ] : null,
            ],
            'location'        => $this->location,
            'city'            => $this->city,
            'country'         => $this->country,
            'address'         => $this->address,
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'directionsUrl'   => $this->directionsUrl(),
            'guests'          => array_values($this->guests ?? []),
            'commentsEnabled' => $this->comments_enabled,
            'images'          => $this->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url])->values(),
            'coverUrl'        => $this->images->first()?->url,
            // Vignette des listes (événements passés) : première image, sinon null.
            'thumbnail_url'   => $this->images->first()?->url,
            // Témoignages publiés (chargés par Event::withPublishedTestimonyCount dans les listes).
            'testimonies_count' => (int) ($this->testimonies_count ?? $this->testimonies()->published()->count()),
            'organizer'       => [
                'id'               => $this->organizer_id,
                'displayName'      => $this->organizer?->organization_name ?: $this->organizer?->display_name,
                'initials'         => $this->organizer?->initials,
                'avatarUrl'        => $this->organizer?->avatar_url,
                'isOrganization'   => (bool) $this->organizer?->isOrganization(),
                'isVerified'       => (bool) $this->organizer?->isVerified(),
                ...\App\Models\User::approvalFieldsFor($this->organizer), // badge-approuve.md
                // ≥ 3 événements confirmés par le sondage et aucun non confirmé (détail seulement).
                'isReliable'       => (bool) ($reliability['reliable'] ?? false),
            ],
            // Modérateurs (détail) : signalements et fiabilité de l'organisateur.
            'moderation' => $detail && $moderator ? [
                'reportCount'    => $this->report_count,
                'pendingReports' => $this->reports()->whereNull('reviewed_at')->count(),
                'reliability'    => $reliability,
            ] : null,
            'stats' => [
                'going'       => $this->going_count,
                'notGoing'    => $this->not_going_count,
                'comments'    => $this->comment_count,
                'testimonies' => (int) ($this->testimonies_count ?? $this->testimoniesVisibleTo($user)->count()),
            ],
            'myParticipation'     => $this->participationOf($user), // going | not_going | null
            'acceptsParticipation' => $this->acceptsParticipation(),
            'canManage'       => $manager,
            // Responsable : supprimer, désigner les co-gestionnaires (organisateur, ses gestionnaires, admin).
            'canAdministrate' => $this->canBeAdministeredBy($user),
            // Supprimer : responsable ou administrateur (jamais un modérateur).
            'canDelete'       => $this->canBeDeletedBy($user),
            // Co-gestionnaires (2 au plus) : visibles des seuls gestionnaires.
            'managers'        => $this->canBeManagedBy($user)
                ? $this->managers->map(fn ($m) => [
                    'id' => $m->id, 'displayName' => $m->display_name,
                    'initials' => $m->initials, 'avatarUrl' => $m->avatar_url,
                ])->values()
                : [],
            'live'            => $live ? ['id' => $live->id, 'status' => $live->status->value, 'title' => $live->title] : null,
            // Direct YouTube (lien collé par l'organisateur) : { id, url, embedUrl, thumbnailUrl } ou null.
            'youtubeLive'     => $this->youtubeLive(),
            'webUrl'          => route('events.show', $this->id),
            // Réactions (j'aime, je prie…) et partage interne « X a partagé » (docs/fonctionnalites/reactions.md).
            ...app(\App\Services\ContentInteractions::class)->payload($this->resource, $user, $request),
            'createdAt'       => $this->created_at?->toIso8601String(),
        ];
    }
}

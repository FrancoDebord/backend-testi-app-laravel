<?php

namespace App\Http\Resources;

use App\Models\PrayerRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Requête de prière, au format de l'API mobile. Autonome : utilisable telle quelle dans les fils
 * (« Pour vous », « Mon fil ») et sur la page d'un événement. Voir docs/fonctionnalites/requetes-de-priere.md
 *
 * Charger `user` et `event`, et `withPrayedBy($viewer)` pour éviter les requêtes en boucle.
 *
 * @mixin PrayerRequest
 */
class PrayerRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer    = $request->user() ?? $request->user('sanctum');
        $isAuthor  = $this->isAuthor($viewer);
        $moderator = (bool) $viewer?->canModerate();
        $author    = $this->revealsAuthorTo($viewer) ? $this->user : null;

        return [
            'type'        => 'prayer_request', // repère pour les fils mélangés
            'id'          => $this->id,
            // Texte lisible (« @Nom ») + mentions cliquables (docs/fonctionnalites/mentions.md).
            'body'        => \App\Services\Mentions::plain($this->body),
            'mentions'    => \App\Services\Mentions::of($this->body),
            'visibility'  => $this->visibility,
            'visibilityLabel' => PrayerRequest::VISIBILITY_LABELS[$this->visibility] ?? $this->visibility,
            'isAnonymous' => $this->is_anonymous,
            // null si la requête est anonyme (sauf pour son auteur et la modération).
            'author'      => $author ? [
                'id'          => $author->id,
                'displayName' => $author->display_name,
                'name'        => $author->display_name,
                'initials'    => $author->initials,
                'avatarUrl'   => $author->avatar_url,
                'avatar'      => $author->avatar_url,
                'isVerified'  => $author->isVerified(),
                'isOrganization' => $author->isOrganization(),
            ] : null,
            'status'      => $this->status,          // open | answered
            'isAnswered'  => $this->isAnswered(),
            'answeredAt'  => $this->answered_at?->toIso8601String(),
            'answerNote'  => $this->answer_note,
            'testimonyId' => $this->testimony_id,
            'prayerCount' => $this->prayer_count,
            'messageCount' => $this->message_count,
            'hasPrayed'   => $this->hasPrayed($viewer),
            'event'       => $this->event_id && $this->event ? ['id' => $this->event_id, 'title' => $this->event->title] : null,
            'dailyVerseId' => $this->daily_verse_id, // verset du jour de rattachement (docs/fonctionnalites/verset-du-jour.md)
            'isMine'      => $isAuthor,
            // Retrait par la modération : seuls l'auteur et la modération voient la requête et ce motif.
            'isHidden'    => ($isAuthor || $moderator) ? $this->isHidden() : false,
            'hiddenReason' => ($isAuthor || $moderator) && $this->isHidden() ? $this->hidden_reason : null,
            'reportCount' => $moderator ? $this->report_count : null,
            'canEdit'     => $isAuthor,
            'canDelete'   => $this->canBeDeletedBy($viewer),
            'canReport'   => $viewer !== null && !$isAuthor,
            'canModerate' => $moderator,
            'canMessage'  => $this->acceptsMessagesFrom($viewer),
            'webUrl'      => route('prayer.requests.show', $this->id),
            // Réactions « j'aime » et partage interne « X a partagé » (« Je prie » : prayerCount / hasPrayed).
            ...app(\App\Services\ContentInteractions::class)->payload($this->resource, $viewer, $request),
            'createdAt'   => $this->created_at?->toIso8601String(),
            'updatedAt'   => $this->updated_at?->toIso8601String(),
        ];
    }
}

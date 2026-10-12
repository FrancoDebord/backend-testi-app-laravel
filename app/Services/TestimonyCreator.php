<?php

namespace App\Services;

use App\Enums\TestimonyStatus;
use App\Enums\TestimonyVisibility;
use App\Http\Requests\Api\StoreTestimonyRequest;
use App\Models\Category;
use App\Models\MediaFile;
use App\Models\Testimony;
use App\Support\YouTube;
use Illuminate\Http\Request;

/**
 * Création d'un témoignage depuis l'API : POST /testimonies et POST /videos/upload-init
 * (vidéo Bunny Stream, docs/fonctionnalites/videos-bunny-stream.md) appliquent les mêmes règles.
 */
class TestimonyCreator
{
    /**
     * @param string|null $videoStatus état initial d'une vidéo Bunny Stream (Testimony::VIDEO_UPLOADING) :
     *                                 type vidéo forcé, media_url et lien YouTube ignorés
     *
     * @throws ContentActionException
     */
    public function create(StoreTestimonyRequest $request, ?string $videoStatus = null): Testimony
    {
        $user = $request->user();
        if (!$user->canPublish()) {
            throw new ContentActionException('Vous n\'avez pas la permission de publier', 403);
        }

        $isBunny = $videoStatus !== null;

        // Vidéo YouTube (docs/fonctionnalites/videos-youtube.md) : administrateurs seulement.
        $youtubeId = null;
        if (!$isBunny && filled($request->youtube_url)) {
            if (!$user->isAdmin()) {
                throw new ContentActionException('Seuls les administrateurs peuvent publier une vidéo YouTube.', 403);
            }
            $youtubeId = YouTube::parseId($request->youtube_url);
            if (!$youtubeId) {
                throw new ContentActionException("Ce lien YouTube n'est pas reconnu.", 422, ['youtube_url' => ["Ce lien YouTube n'est pas reconnu."]]);
            }
        }

        // Rattachement à un événement : seuls ses gestionnaires (docs/fonctionnalites/evenements.md).
        try {
            $event = app(EventService::class)->ensureCanAttach($request->event_id, $user);
        } catch (EventActionException $e) {
            throw new ContentActionException($e->getMessage(), $e->status());
        }

        // Parole prophétique accomplie : elle doit appartenir à l'auteur (docs/fonctionnalites/paroles-prophetiques.md).
        $prophecy = null;
        if (filled($request->prophecy_id)) {
            $prophecy = $user->prophecies()->find($request->prophecy_id);
            if (!$prophecy) {
                throw new ContentActionException('Parole prophétique introuvable.', 404);
            }
        }

        $isJournal    = $request->visibility === TestimonyVisibility::Private->value;
        $categorySlug = $request->category ?: 'autre';
        $category     = Category::where('slug', $categorySlug)->first();
        $mediaUrl     = ($isBunny || $youtubeId) ? null : $request->media_url;

        // Versions déjà produites pour ce fichier (docs/fonctionnalites/qualites-media.md).
        $media = self::processedMediaFor($mediaUrl);

        $testimony = Testimony::create([
            'user_id'      => $user->id,
            'category_id'  => $category?->id,
            'event_id'     => $event?->id,
            'title'        => $request->title,
            'type'         => ($isBunny || $youtubeId) ? 'video' : $request->type,
            'category_slug' => $categorySlug,
            // Mentions « @ » validées ; prévenues à l'approbation (Mentions::notifyTestimony).
            'body_text'    => self::bodyWithMentions($request),
            'media_url'    => $mediaUrl,
            'youtube_id'   => $youtubeId,
            'video_status' => $videoStatus,
            'proofs_public' => $request->boolean('proofs_public'),
            'renditions'   => $media?->renditions,
            'cover_url'    => $request->cover_url ?: ($youtubeId ? YouTube::thumbnailUrl($youtubeId) : null),
            'duration_sec' => ($request->duration ?: $media?->duration_sec) ?? 0,
            'bible_verse'  => $request->bible_verse,
            'bible_ref'    => $request->verse_ref,
            'tags'         => $request->tags ?? [],
            'visibility'   => $request->visibility ?? 'public',
            // Carnet privé : brouillon, jamais soumis à la modération.
            'status'       => ($isJournal ? TestimonyStatus::Draft : TestimonyStatus::Pending)->value,
        ]);

        $testimony->load('user');

        if ($prophecy) {
            // Montrée avec le témoignage (une fois publié), si l'auteur le souhaite.
            app(Prophecies::class)->attachTestimony($prophecy, $testimony->id, !$isJournal && $request->boolean('prophecy_public'));
            $testimony->setRelation('prophecy', $prophecy);
        }

        if (!$isJournal) {
            $category?->increment('testimony_count');
        }

        return $testimony;
    }

    /**
     * Texte du témoignage avec mentions « @ » validées (jetons `@[Nom](user:id)`, ou « @Nom » + mention_ids) ;
     * null si body_text n'est pas envoyé. Voir docs/fonctionnalites/mentions.md
     */
    public static function bodyWithMentions(Request $request, ?string $previous = null): ?string
    {
        $body = $request->input('body_text');
        if (!is_string($body)) {
            return null;
        }

        return Mentions::prepare($body, (array) $request->input('mention_ids', []), $previous)['text'];
    }

    /** Fichier média déjà converti correspondant à media_url (sinon null : la tâche mettra le témoignage à jour). */
    public static function processedMediaFor(?string $mediaUrl): ?MediaFile
    {
        $media = MediaFile::findForUrl($mediaUrl);

        return $media?->isProcessed() ? $media : null;
    }
}

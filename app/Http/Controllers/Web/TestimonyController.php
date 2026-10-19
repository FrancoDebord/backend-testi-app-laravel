<?php

namespace App\Http\Controllers\Web;

use App\Enums\TestimonyStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Comment;
use App\Models\MediaFile;
use App\Models\Reaction;
use App\Models\Testimony;
use Illuminate\Http\JsonResponse;
use App\Services\TestimonyProofs;
use App\Support\YouTube;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TestimonyController extends Controller
{
    public function show(Request $request, string $id): View|JsonResponse
    {
        $testimony = Testimony::with(['user', 'category', 'liveSession:id,testimony_id,started_at', 'mediaFile:id,url,processing_status'])->find($id);

        if (!$testimony) abort(404);

        // Carnet privé : 404 pour toute autre personne que l'auteur (son existence n'est pas révélée).
        if ($testimony->isInJournal() && Auth::id() !== $testimony->user_id) {
            abort(404);
        }

        $watch = app(\App\Services\WatchPage::class);

        // « Afficher plus de commentaires »
        if ($request->expectsJson()) {
            return $watch->commentsResponse($testimony, 'testimonies.show');
        }

        // Une vue par personne et par période (même règle que la page Vidéos).
        app(\App\Services\ViewCounter::class)->record($testimony, $request);

        // Même page de lecture que /videos/{id} (docs/fonctionnalites/videos.md).
        return view('testimonies.show', $watch->data($testimony, $request->user(), 'testimonies.show'));
    }

    public function create(Request $request): View
    {
        $categories = Category::active()->get();
        // Témoignage officiel d'un événement (?event=) : gestionnaires seulement (docs/fonctionnalites/evenements.md).
        $event = $this->attachableEvent($request->query('event'), $request);
        // Témoignage de l'accomplissement d'une parole du carnet (?prophecy=) : son auteur seulement.
        $prophecy = filled($request->query('prophecy'))
            ? app(\App\Services\Prophecies::class)->find($request->user(), $request->query('prophecy'))
            : null;

        // Vidéo envoyée directement à Bunny Stream par le navigateur (docs/fonctionnalites/videos-bunny-stream.md).
        $bunnyVideos = app(\App\Services\BunnyStream::class)->isConfigured();

        // Témoignage de conversion (docs/fonctionnalites/temoignage-de-conversion.md) : compte personnel, un seul.
        $conversionTestimony = app(\App\Services\ConversionTestimonies::class)->of($request->user());
        $canConversion = !$conversionTestimony && \App\Services\ConversionTestimonies::canPublish($request->user());
        $kind = $canConversion && $request->query('kind') === 'conversion' ? 'conversion' : 'standard';

        // Types que ce compte peut publier (docs/fonctionnalites/types-de-temoignages.md) : les autres sont masqués.
        $allowedTypes = app(\App\Services\TestimonyTypePolicy::class)->allowed($request->user());

        // Témoigner sur un direct terminé (?live=, docs/fonctionnalites/lives.md) : ignoré s'il n'est pas terminé ou visible.
        $live = $this->linkableLive(old('live_id', $request->query('live')), $request);

        return view('testimonies.create', compact('categories', 'event', 'prophecy', 'bunnyVideos', 'conversionTestimony', 'canConversion', 'kind', 'allowedTypes', 'live'));
    }

    /** Direct terminé et visible auquel rattacher le témoignage, sinon null (pré-remplissage du formulaire). */
    private function linkableLive(mixed $liveId, Request $request): ?\App\Models\LiveSession
    {
        try {
            return app(\App\Services\LiveTestimonies::class)->resolve(is_string($liveId) ? $liveId : null, $request->user());
        } catch (\App\Services\ContentActionException) {
            return null;
        }
    }

    /** Événement auquel rattacher le témoignage ; refuse si la personne n'en est pas gestionnaire. */
    private function attachableEvent(?string $eventId, Request $request): ?\App\Models\Event
    {
        if (blank($eventId)) {
            return null;
        }
        abort_unless(\Illuminate\Support\Str::isUuid($eventId), 404);
        try {
            return app(\App\Services\EventService::class)->ensureCanAttach($eventId, $request->user());
        } catch (\App\Services\EventActionException $e) {
            abort($e->status() === 404 ? 404 : 403, $e->getMessage());
        }
    }

    /**
     * Publication depuis le site. Vidéo : envoyée DIRECTEMENT par le navigateur à Bunny Stream
     * (bunny_upload=1, sans fichier) ; la réponse JSON donne l'autorisation TUS, puis le navigateur appelle
     * upload-complete (docs/fonctionnalites/videos-bunny-stream.md). Audio : fichier enregistré sur le serveur.
     */
    public function store(Request $request, TestimonyProofs $proofs): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'title'         => 'required|string|max:200',
            'type'          => 'required|in:text,audio,video,youtube',
            // Témoignage de conversion : catégorie « conversion » par défaut.
            'category'      => 'nullable|required_unless:kind,conversion|string|exists:categories,slug',
            'body_text'     => 'nullable|required_if:type,text|string',
            'bible_verse'   => 'nullable|string|max:500',
            'bible_ref'     => 'nullable|string|max:100',
            'tags'          => 'nullable|string',
            'visibility'    => 'nullable|in:public,private,followers',
            'cover'         => 'nullable|image|max:5120',
            'media_file'    => 'nullable|file|max:102400|mimetypes:audio/*,video/*',
            // Lien YouTube : administrateurs seulement (docs/fonctionnalites/videos-youtube.md)
            'youtube_url'   => 'nullable|required_if:type,youtube|string|max:300',
            // Preuves : 2 images ou PDF au plus (docs/fonctionnalites/preuves.md)
            'proof_1'       => TestimonyProofs::rules(),
            'proof_2'       => TestimonyProofs::rules(),
            'proofs_public' => 'nullable|boolean',
            'consent_given' => 'accepted',
            'event_id'      => 'nullable|uuid',
            // Parole prophétique accomplie (docs/fonctionnalites/paroles-prophetiques.md)
            'prophecy_id'     => 'nullable|uuid',
            'prophecy_public' => 'nullable|boolean',
            // Mentions « @ » choisies dans le texte (docs/fonctionnalites/mentions.md)
            'mention_ids'     => 'nullable|array|max:20',
            'mention_ids.*'   => 'nullable|string',
            // Vidéo envoyée directement à Bunny Stream par le navigateur (resources/js/bunny-upload.js)
            'bunny_upload'    => 'nullable|boolean',
            // Témoignage de conversion : kind, conversion_year, conversion_place, conversion_verse
            ...\App\Services\ConversionTestimonies::rules(),
            // Témoignage sur un direct terminé (docs/fonctionnalites/lives.md)
            ...\App\Services\LiveTestimonies::rules(),
            // Crédit de la vidéo YouTube d'origine (docs/fonctionnalites/videos-youtube.md)
            ...\App\Support\YouTubeCredit::rules(),
        ], \App\Services\ConversionTestimonies::messages() + \App\Services\LiveTestimonies::messages() + \App\Support\YouTubeCredit::messages() + TestimonyProofs::messages('proof_1') + TestimonyProofs::messages('proof_2') + [
            'title.required'         => __('testimonies.validation.title_required'),
            'youtube_url.required_if' => __('testimonies.validation.youtube_required'),
            'category.required'      => __('testimonies.validation.category_required'),
            'category.required_unless' => __('testimonies.validation.category_required'),
            'category.exists'        => __('testimonies.validation.category_exists'),
            'body_text.required_if'  => __('testimonies.create.body_required'),
            'cover.image'            => __('testimonies.validation.cover_image'),
            'cover.max'              => __('testimonies.validation.cover_max'),
            'media_file.max'         => __('testimonies.validation.media_max'),
            'media_file.mimetypes'   => __('testimonies.validation.media_mimetypes'),
            'consent_given.accepted' => __('testimonies.validation.consent'),
        ]);

        // Types autorisés (docs/fonctionnalites/types-de-temoignages.md) : même règle que l'API, carnet privé compris.
        $typePolicy = app(\App\Services\TestimonyTypePolicy::class);
        $requestedType = \App\Services\TestimonyTypePolicy::requestedType($data['type'], $data['youtube_url'] ?? null);
        try {
            $typePolicy->ensureAllowed($request->user(), $requestedType);
        } catch (\App\Services\ContentActionException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 403);
            }
            // Formulaire classique : message sous le choix du type (et sous le lien YouTube le cas échéant).
            $errors = ['type' => $e->errors()['type'][0] ?? $e->getMessage()];
            if ($requestedType === 'youtube') {
                $errors['youtube_url'] = $e->getMessage();
            }

            return back()->withInput()->withErrors($errors);
        }

        $event     = $this->attachableEvent($data['event_id'] ?? null, $request);
        // Direct terminé et visible, sinon erreur sous le champ (même règle que l'API).
        try {
            $live = app(\App\Services\LiveTestimonies::class)->resolve($data['live_id'] ?? null, $request->user());
        } catch (\App\Services\ContentActionException $e) {
            throw ValidationException::withMessages(['live_id' => $e->getMessage()]);
        }
        $prophecies = app(\App\Services\Prophecies::class);
        $prophecy  = filled($data['prophecy_id'] ?? null) ? $prophecies->find($request->user(), $data['prophecy_id']) : null;
        $coverUrl  = null;
        $mediaUrl  = null;
        $media     = null;
        $youtubeId = $this->youtubeIdFrom($request, $data);
        if ($youtubeId) {
            $data['type'] = 'video';
        }
        $bunnyUpload = !$youtubeId && $data['type'] === 'video' && $request->boolean('bunny_upload');
        if ($bunnyUpload && !app(\App\Services\BunnyStream::class)->isConfigured()) {
            return response()->json(['message' => __('videos.upload.unavailable')], 503);
        }

        if ($request->hasFile('cover')) {
            $path     = $request->file('cover')->store('covers', 'public');
            $coverUrl = asset('storage/' . $path);
        }

        if (!$youtubeId && !$bunnyUpload && $request->hasFile('media_file')) {
            $file     = $request->file('media_file');
            $path     = $file->store('media', 'public');
            $mediaUrl = asset('storage/' . $path);
            $mimeType = (string) $file->getMimeType();

            $media = MediaFile::create([
                'user_id'       => Auth::id(),
                'disk'          => 'public',
                'path'          => $path,
                'url'           => $mediaUrl,
                'mime_type'     => mb_substr($mimeType, 0, 50),
                'type'          => str_starts_with($mimeType, 'video/') ? 'video' : 'audio',
                'size_bytes'    => $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
            ]);
        }

        $kindFields   = \App\Services\ConversionTestimonies::attributesFor($request);
        $isConversion = $kindFields['kind'] === \App\Enums\TestimonyKind::Conversion->value;
        $data['category'] = ($data['category'] ?? null) ?: ($isConversion && Category::where('slug', 'conversion')->exists() ? 'conversion' : 'autre');
        $category  = Category::where('slug', $data['category'])->first();
        $isJournal = ($data['visibility'] ?? 'public') === 'private';
        $tags     = !empty($data['tags']) ? array_map('trim', explode(',', $data['tags'])) : [];

        $create = fn () => Testimony::create([
            ...$kindFields,
            'user_id'       => Auth::id(),
            'category_id'   => $category?->id,
            'event_id'      => $event?->id,
            'live_id'       => $live?->id,
            'title'         => $data['title'],
            'type'          => $data['type'],
            'category_slug' => $data['category'],
            // Mentions « @ » validées ; prévenues à l'approbation (Mentions::notifyTestimony).
            'body_text'     => isset($data['body_text'])
                ? \App\Services\Mentions::prepare($data['body_text'], (array) ($data['mention_ids'] ?? []))['text'] : null,
            'cover_url'     => $coverUrl ?? ($youtubeId ? YouTube::thumbnailUrl($youtubeId) : null),
            'media_url'     => $mediaUrl,
            'youtube_id'    => $youtubeId,
            // Crédit de la vidéo d'origine : vidéo YouTube seulement (docs/fonctionnalites/videos-youtube.md).
            ...\App\Support\YouTubeCredit::attributes($request, $youtubeId !== null),
            'proofs_public' => $request->boolean('proofs_public'),
            'bible_verse'   => $data['bible_verse'] ?? null,
            'bible_ref'     => $data['bible_ref'] ?? null,
            'tags'          => $tags,
            'visibility'    => $data['visibility'] ?? 'public',
            // Carnet privé (docs/fonctionnalites/carnet-prive.md) : jamais soumis à la modération, comme dans l'API.
            'status'        => ($isJournal ? TestimonyStatus::Draft : TestimonyStatus::Pending)->value,
            // Vidéo Bunny : en cours d'envoi, hors des listes jusqu'à ce qu'elle soit lisible.
            'video_status'  => $bunnyUpload ? Testimony::VIDEO_UPLOADING : null,
        ]);
        // Conversion : compte personnel, un seul par compte, vérifié sous verrou (même règle que l'API).
        try {
            $testimony = app(\App\Services\ConversionTestimonies::class)->create($request->user(), $isConversion, $create);
        } catch (\App\Services\ContentActionException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status())
                : back()->withInput()->withErrors(['kind' => $e->getMessage()]);
        }

        // Vidéo Bunny : création chez Bunny et autorisation d'envoi ; échec → rien n'est gardé.
        $upload = null;
        if ($bunnyUpload) {
            try {
                $upload = app(\App\Services\BunnyStreamVideos::class)->initUpload($testimony);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Bunny Stream : création de la vidéo impossible (site).', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);
                $testimony->forceDelete();

                return response()->json(['message' => __('videos.upload.init_failed')], 502);
            }
        }

        // Modération non obligatoire (config testimonies.require_moderation) : approuvé aussitôt (vidéo Bunny : une fois lisible).
        if (!$bunnyUpload) {
            app(\App\Services\TestimonyModeration::class)->submitted($testimony);
        }

        // Parole accomplie : montrée avec le témoignage une fois publié, si l'auteur le souhaite (jamais pour le carnet).
        if ($prophecy) {
            $prophecies->attachTestimony($prophecy, $testimony->id, !$isJournal && $request->boolean('prophecy_public'));
        }

        // Versions allégées produites en file d'attente, puis recopiées sur le témoignage
        // (docs/fonctionnalites/qualites-media.md).
        $media?->queueTranscoding();

        foreach ([1, 2] as $position) {
            if ($request->hasFile("proof_{$position}")) {
                $proofs->add($testimony, $request->file("proof_{$position}"), $request->user(), $position);
            }
        }

        if ($upload) {
            // Le navigateur envoie le fichier à Bunny (TUS), puis appelle complete_url (resources/js/bunny-upload.js).
            return response()->json([
                'testimony_id'  => $testimony->id,
                'upload'        => $upload,
                'signature_url' => route('testimonies.video.signature', $testimony->id),
                'complete_url'  => route('testimonies.video.complete', $testimony->id),
                'redirect'      => route('testimonies.show', $testimony->id),
            ], 201);
        }

        return redirect()->route('testimonies.show', $testimony->id)
                         ->with('success', match (true) {
                             $isJournal => __('testimonies.flash.saved_in_journal'),
                             $testimony->status === TestimonyStatus::Approved => __('testimonies.flash.published'),
                             default => __('testimonies.flash.submitted'),
                         });
    }

    /** Lien YouTube du formulaire : identifiant reconnu (droit de publier vérifié par TestimonyTypePolicy). */
    private function youtubeIdFrom(Request $request, array $data): ?string
    {
        if (blank($data['youtube_url'] ?? null)) {
            return null;
        }

        return YouTube::parseId($data['youtube_url'])
            ?? throw ValidationException::withMessages(['youtube_url' => __('testimonies.validation.youtube_invalid')]);
    }

    /** Preuve d'un témoignage (affichée dans le navigateur) : auteur et équipe de modération seulement. */
    public function proof(Request $request, string $id, string $proofId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $testimony = Testimony::withTrashed()->findOrFail($id);
        abort_unless(TestimonyProofs::canView($request->user(), $testimony), 403);
        $proof = $testimony->proofs()->whereKey($proofId)->firstOrFail();

        return \Illuminate\Support\Facades\Storage::disk($proof->disk)->response($proof->path, $proof->original_name, [
            'Content-Type'           => $proof->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => TestimonyProofs::arePublic($testimony) ? 'public, max-age=300' : 'private, max-age=300',
        ], 'inline');
    }

    public function myTestimonies(Request $request): View
    {
        $status = $request->query('status', 'all');
        $query  = Testimony::with(['user', 'category'])->where('user_id', Auth::id())->latest();

        if ($status !== 'all') $query->where('status', $status);

        $testimonies = $query->paginate(12);
        return view('testimonies.mine', compact('testimonies', 'status'));
    }

    /** Supprimer un témoignage : son auteur ou un administrateur (jamais un modérateur). */
    public function destroy(string $id): RedirectResponse
    {
        $testimony = Testimony::findOrFail($id);
        abort_unless($testimony->canBeDeletedBy(Auth::user()), 403);

        $mine = $testimony->user_id === Auth::id();
        $testimony->delete();

        return redirect()->route($mine ? 'testimonies.mine' : 'home')->with('success', __('testimonies.flash.deleted'));
    }

    public function report(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'reason'  => 'required|string',
            'details' => 'nullable|string|max:500',
        ]);

        Testimony::findOrFail($id);

        \App\Models\TestimonyReport::firstOrCreate(
            ['testimony_id' => $id, 'reporter_id' => Auth::id()],
            ['reason' => $request->reason, 'details' => $request->details]
        );

        return back()->with('success', __('testimonies.flash.reported'));
    }

    /**
     * Temps de prière (fetch) avant « Je prie » : même réponse JSON que l'API POST testimonies/{id}/pray/start.
     * Voir docs/fonctionnalites/temps-de-priere.md
     */
    public function startPrayer(string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        $data = $testimony ? app(\App\Services\TestimonyReactions::class)->startPrayer(Auth::user(), $testimony) : null;
        if ($data === null) {
            return response()->json(['success' => false, 'message' => __('prayer_focus.not_found')], 404);
        }

        return response()->json(['success' => true, 'data' => $data, 'message' => __('prayer_focus.started')]);
    }

    public function storeReaction(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $request->validate(['type' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\ReactionType::class)]]);

        $testimony = Testimony::findOrFail($id);

        // Une réaction par groupe (j'aime / je prie) : docs/fonctionnalites/reactions.md. « Je prie » se comporte
        // comme dans l'application : un seul bouton qui pose ou retire la prière (pray, amen et worship confondus).
        $type = \App\Enums\ReactionType::from($request->type);
        $service = app(\App\Services\TestimonyReactions::class);
        $reacted = $type->group() === 'prayer'
            ? $service->togglePrayer(Auth::user(), $testimony)
            : $service->toggle(Auth::user(), $testimony, $type);

        $testimony->refresh();

        if ($request->ajax()) {
            $state = $service->state(Auth::user(), $testimony);

            return response()->json([
                'reacted'       => $reacted,
                // État comme l'API de l'application : isPrayedByMe, isLikedByMe.
                'is_prayed'     => $state['isPrayedByMe'],
                'is_liked'      => $state['isLikedByMe'],
                // Réactions de la personne après le changement (une par groupe) : boutons à jour.
                'my_reactions'  => Reaction::where('user_id', Auth::id())->where('testimony_id', $testimony->id)
                    ->pluck('type')->map(fn ($t) => $t->value)->values(),
                'like_count'    => $testimony->like_count,
                'prayer_count'  => $testimony->prayer_count,
            ]);
        }

        return back();
    }

    public function saveTestimony(string $id): JsonResponse|RedirectResponse
    {
        Testimony::findOrFail($id);

        $saved = DB::table('saved_testimonies')
            ->where('user_id', Auth::id())
            ->where('testimony_id', $id)
            ->exists();

        if ($saved) {
            DB::table('saved_testimonies')
                ->where('user_id', Auth::id())
                ->where('testimony_id', $id)
                ->delete();
        } else {
            DB::table('saved_testimonies')->insert([
                'user_id'      => Auth::id(),
                'testimony_id' => $id,
                'saved_at'     => now(),
            ]);
        }

        if (request()->ajax()) {
            return response()->json(['saved' => !$saved]);
        }

        return back();
    }

    public function storeComment(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $request->validate(['body' => 'required|string|max:2000']);

        $testimony = Testimony::findOrFail($id);
        if (!$testimony->acceptsCommentsFrom(Auth::user())) {
            return $request->expectsJson()
                ? response()->json(['message' => Testimony::commentsClosedMessage()], 403)
                : back()->with('error', Testimony::commentsClosedMessage());
        }

        // Mentions « @ » (docs/fonctionnalites/mentions.md)
        $mentions = \App\Services\Mentions::prepare((string) $request->body);

        $comment = Comment::create([
            'testimony_id' => $id,
            'user_id'      => Auth::id(),
            'body'         => $mentions['text'],
        ]);

        $testimony->increment('comment_count');
        \App\Services\Mentions::notify($mentions['ids'], Auth::user(), [
            'testimony_id' => $testimony->id, 'testimony_title' => $testimony->title, 'comment_id' => $comment->id,
        ]);

        if ($request->ajax()) {
            $user = Auth::user();
            return response()->json([
                'comment' => [
                    'id'           => $comment->id,
                    'body'         => \App\Services\Mentions::plain($comment->body),
                    'display_name' => $user->display_name,
                    'initials'     => $user->initials,
                ],
            ]);
        }

        return back()->with('success', __('comments.added'));
    }
}

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

        return view('testimonies.create', compact('categories', 'event', 'prophecy'));
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

    public function store(Request $request, TestimonyProofs $proofs): RedirectResponse
    {
        $data = $request->validate([
            'title'         => 'required|string|max:200',
            'type'          => 'required|in:text,audio,video,youtube',
            'category'      => 'required|string|exists:categories,slug',
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
        ], TestimonyProofs::messages('proof_1') + TestimonyProofs::messages('proof_2') + [
            'title.required'         => 'Merci de donner un titre à votre témoignage.',
            'youtube_url.required_if' => 'Collez le lien de la vidéo YouTube.',
            'category.required'      => 'Merci de choisir une catégorie.',
            'category.exists'        => "La catégorie choisie n'existe plus. Merci d'en choisir une autre.",
            'body_text.required_if'  => 'Merci de rédiger votre témoignage.',
            'cover.image'            => "L'image de couverture doit être une image (JPG, PNG…).",
            'cover.max'              => "L'image de couverture ne doit pas dépasser 5 Mo.",
            'media_file.max'         => 'Le fichier ne doit pas dépasser 100 Mo.',
            'media_file.mimetypes'   => 'Le fichier doit être un enregistrement audio ou vidéo.',
            'consent_given.accepted' => 'Merci de confirmer votre engagement avant l\'envoi.',
        ]);

        $event     = $this->attachableEvent($data['event_id'] ?? null, $request);
        $prophecies = app(\App\Services\Prophecies::class);
        $prophecy  = filled($data['prophecy_id'] ?? null) ? $prophecies->find($request->user(), $data['prophecy_id']) : null;
        $coverUrl  = null;
        $mediaUrl  = null;
        $media     = null;
        $youtubeId = $this->youtubeIdFrom($request, $data);
        if ($youtubeId) {
            $data['type'] = 'video';
        }

        if ($request->hasFile('cover')) {
            $path     = $request->file('cover')->store('covers', 'public');
            $coverUrl = asset('storage/' . $path);
        }

        if (!$youtubeId && $request->hasFile('media_file')) {
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

        $category  = Category::where('slug', $data['category'])->first();
        $isJournal = ($data['visibility'] ?? 'public') === 'private';
        $tags     = !empty($data['tags']) ? array_map('trim', explode(',', $data['tags'])) : [];

        $testimony = Testimony::create([
            'user_id'       => Auth::id(),
            'category_id'   => $category?->id,
            'event_id'      => $event?->id,
            'title'         => $data['title'],
            'type'          => $data['type'],
            'category_slug' => $data['category'],
            'body_text'     => $data['body_text'] ?? null,
            'cover_url'     => $coverUrl ?? ($youtubeId ? YouTube::thumbnailUrl($youtubeId) : null),
            'media_url'     => $mediaUrl,
            'youtube_id'    => $youtubeId,
            'proofs_public' => $request->boolean('proofs_public'),
            'bible_verse'   => $data['bible_verse'] ?? null,
            'bible_ref'     => $data['bible_ref'] ?? null,
            'tags'          => $tags,
            'visibility'    => $data['visibility'] ?? 'public',
            // Carnet privé (docs/fonctionnalites/carnet-prive.md) : jamais soumis à la modération, comme dans l'API.
            'status'        => ($isJournal ? TestimonyStatus::Draft : TestimonyStatus::Pending)->value,
        ]);

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

        return redirect()->route('testimonies.show', $testimony->id)
                         ->with('success', $isJournal ? 'Témoignage enregistré dans votre carnet privé.' : 'Témoignage soumis pour modération.');
    }

    /** Lien YouTube du formulaire : administrateurs seulement, identifiant reconnu. */
    private function youtubeIdFrom(Request $request, array $data): ?string
    {
        if (blank($data['youtube_url'] ?? null)) {
            return null;
        }
        if (!$request->user()->isAdmin()) {
            throw ValidationException::withMessages(['youtube_url' => 'Seuls les administrateurs peuvent publier une vidéo YouTube.']);
        }

        return YouTube::parseId($data['youtube_url'])
            ?? throw ValidationException::withMessages(['youtube_url' => "Ce lien YouTube n'est pas reconnu (exemple : https://www.youtube.com/watch?v=…)."]);
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

        return back()->with('success', 'Signalement envoyé.');
    }

    public function storeReaction(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $request->validate(['type' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\ReactionType::class)]]);

        $testimony = Testimony::findOrFail($id);

        $existing = Reaction::where([
            'user_id'      => Auth::id(),
            'testimony_id' => $id,
            'type'         => $request->type,
        ])->first();

        if ($existing) {
            $field = $existing->type->counterField();
            $testimony->decrement($field);
            $existing->delete();
            $reacted = false;
        } else {
            Reaction::create([
                'user_id'      => Auth::id(),
                'testimony_id' => $id,
                'type'         => $request->type,
            ]);
            $field = \App\Enums\ReactionType::from($request->type)->counterField();
            $testimony->increment($field);
            $reacted = true;
        }

        $testimony->refresh();

        if ($request->ajax()) {
            return response()->json([
                'reacted'       => $reacted,
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

        $comment = Comment::create([
            'testimony_id' => $id,
            'user_id'      => Auth::id(),
            'body'         => $request->body,
        ]);

        $testimony->increment('comment_count');

        if ($request->ajax()) {
            $user = Auth::user();
            return response()->json([
                'comment' => [
                    'id'           => $comment->id,
                    'body'         => $comment->body,
                    'display_name' => $user->display_name,
                    'initials'     => $user->initials,
                ],
            ]);
        }

        return back()->with('success', 'Commentaire ajouté.');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Models\VerseBookmark;
use App\Models\VerseHighlight;
use App\Models\VerseNote;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibleAnnotationController extends Controller
{
    use ApiResponse;

    // ── Annotations d'un chapitre ────────────────────────────────────────

    // GET /bible/{book}/{chapter}/annotations?translation=LSG
    // Retourne signets, surlignages et notes de l'utilisateur pour un chapitre
    public function chapter(Request $request, int $book, int $chapter): JsonResponse
    {
        $userId      = $request->user()->id;
        $translation = strtoupper($request->query('translation', 'LSG'));

        $base = ['user_id' => $userId, 'translation' => $translation, 'book' => $book, 'chapter' => $chapter];

        $bookmarks  = VerseBookmark::where($base)->get(['id', 'verse', 'tag', 'created_at']);
        $highlights = VerseHighlight::where($base)->get(['id', 'verse', 'color']);
        $notes      = VerseNote::where($base)->get(['id', 'verse', 'note', 'updated_at']);

        return $this->success([
            'bookmarks'  => $bookmarks,
            'highlights' => $highlights,
            'notes'      => $notes,
        ]);
    }

    // ── Signets ──────────────────────────────────────────────────────────

    // GET /bible/bookmarks?translation=LSG
    public function bookmarks(Request $request): JsonResponse
    {
        $userId      = $request->user()->id;
        $translation = $request->query('translation');

        $query = VerseBookmark::where('user_id', $userId)->latest('created_at');

        if ($translation) {
            $query->where('translation', strtoupper($translation));
        }

        $bookmarks = $query->get();

        // Enrichir avec le texte du verset et le nom du livre
        $enriched = $bookmarks->map(function ($b) {
            $verse = BibleVerse::where('translation', $b->translation)
                               ->where('book', $b->book)
                               ->where('chapter', $b->chapter)
                               ->where('verse', $b->verse)
                               ->value('text');

            $bookName = BibleBook::where('translation', $b->translation)
                                 ->where('number', $b->book)
                                 ->value('name');

            return [
                'id'          => $b->id,
                'translation' => $b->translation,
                'book'        => $b->book,
                'bookName'    => $bookName,
                'chapter'     => $b->chapter,
                'verse'       => $b->verse,
                'verseText'   => $verse,
                'reference'   => "{$bookName} {$b->chapter}:{$b->verse}",
                'tag'         => $b->tag,
                'createdAt'   => $b->created_at,
            ];
        });

        return $this->success($enriched);
    }

    // POST /bible/bookmarks
    public function storeBookmark(Request $request): JsonResponse
    {
        $request->validate([
            'translation' => 'required|string|max:10',
            'book'        => 'required|integer|min:1|max:66',
            'chapter'     => 'required|integer|min:1',
            'verse'       => 'required|integer|min:1',
            'tag'         => 'nullable|string|max:50',
        ]);

        $bookmark = VerseBookmark::firstOrCreate(
            [
                'user_id'     => $request->user()->id,
                'translation' => strtoupper($request->translation),
                'book'        => $request->book,
                'chapter'     => $request->chapter,
                'verse'       => $request->verse,
            ],
            ['tag' => $request->tag]
        );

        if (!$bookmark->wasRecentlyCreated && $request->has('tag')) {
            $bookmark->update(['tag' => $request->tag]);
        }

        return $this->success($bookmark, $bookmark->wasRecentlyCreated ? 'Verset marqué' : 'Étiquette mise à jour');
    }

    // DELETE /bible/bookmarks/{id}
    public function destroyBookmark(Request $request, int $id): JsonResponse
    {
        $deleted = VerseBookmark::where('id', $id)
                                ->where('user_id', $request->user()->id)
                                ->delete();

        return $deleted ? $this->success(null, 'Signet supprimé') : $this->notFound();
    }

    // ── Surlignages ──────────────────────────────────────────────────────

    // POST /bible/highlights  — crée ou met à jour (upsert)
    public function storeHighlight(Request $request): JsonResponse
    {
        $request->validate([
            'translation' => 'required|string|max:10',
            'book'        => 'required|integer|min:1|max:66',
            'chapter'     => 'required|integer|min:1',
            'verse'       => 'required|integer|min:1',
            'color'       => 'required|in:yellow,green,blue,pink,orange',
        ]);

        $highlight = VerseHighlight::updateOrCreate(
            [
                'user_id'     => $request->user()->id,
                'translation' => strtoupper($request->translation),
                'book'        => $request->book,
                'chapter'     => $request->chapter,
                'verse'       => $request->verse,
            ],
            ['color' => $request->color]
        );

        return $this->success($highlight, 'Surlignage enregistré');
    }

    // DELETE /bible/highlights/{id}
    public function destroyHighlight(Request $request, int $id): JsonResponse
    {
        $deleted = VerseHighlight::where('id', $id)
                                 ->where('user_id', $request->user()->id)
                                 ->delete();

        return $deleted ? $this->success(null, 'Surlignage supprimé') : $this->notFound();
    }

    // ── Notes ────────────────────────────────────────────────────────────

    // POST /bible/notes  — crée ou met à jour
    public function storeNote(Request $request): JsonResponse
    {
        $request->validate([
            'translation' => 'required|string|max:10',
            'book'        => 'required|integer|min:1|max:66',
            'chapter'     => 'required|integer|min:1',
            'verse'       => 'required|integer|min:1',
            'note'        => 'required|string|max:2000',
        ]);

        $note = VerseNote::updateOrCreate(
            [
                'user_id'     => $request->user()->id,
                'translation' => strtoupper($request->translation),
                'book'        => $request->book,
                'chapter'     => $request->chapter,
                'verse'       => $request->verse,
            ],
            ['note' => $request->note]
        );

        return $this->success($note, 'Note enregistrée');
    }

    // DELETE /bible/notes/{id}
    public function destroyNote(Request $request, int $id): JsonResponse
    {
        $deleted = VerseNote::where('id', $id)
                            ->where('user_id', $request->user()->id)
                            ->delete();

        return $deleted ? $this->success(null, 'Note supprimée') : $this->notFound();
    }

    // ── Partage ──────────────────────────────────────────────────────────

    // GET /bible/{book}/{chapter}/{verse}/share?translation=LSG
    // Retourne un verset formaté pour le partage (texte + référence + lien deeplink)
    public function shareVerse(Request $request, int $book, int $chapter, int $verse): JsonResponse
    {
        $translation = strtoupper($request->query('translation', 'LSG'));

        $v = BibleVerse::where('translation', $translation)
                       ->where('book', $book)
                       ->where('chapter', $chapter)
                       ->where('verse', $verse)
                       ->first();

        if (!$v) return $this->notFound();

        $bookName  = BibleBook::where('translation', $translation)->where('number', $book)->value('name');
        $reference = "{$bookName} {$chapter}:{$verse} ({$translation})";

        return $this->success([
            'text'      => $v->text,
            'reference' => $reference,
            'shareText' => "\"{$v->text}\"\n— {$reference}",
        ]);
    }
}

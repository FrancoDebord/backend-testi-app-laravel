<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BibleBookResource;
use App\Http\Resources\BibleVerseResource;
use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibleController extends Controller
{
    use ApiResponse;

    private function translation(Request $request): string
    {
        return strtoupper($request->query('translation', 'LSG'));
    }

    // GET /bible/books?translation=LSG
    public function books(Request $request): JsonResponse
    {
        $books = BibleBook::where('translation', $this->translation($request))
                          ->orderBy('number')
                          ->get();

        if ($books->isEmpty()) {
            return $this->error('Aucune donnée pour cette traduction. Lancez : php artisan bible:import', 404);
        }

        return $this->success(BibleBookResource::collection($books));
    }

    // GET /bible/{book}/{chapter}?translation=LSG
    // Si l'utilisateur est authentifié, retourne aussi ses annotations pour ce chapitre
    public function chapter(Request $request, int $book, int $chapter): JsonResponse
    {
        $translation = $this->translation($request);

        $verses = BibleVerse::where('translation', $translation)
                            ->where('book', $book)
                            ->where('chapter', $chapter)
                            ->orderBy('verse')
                            ->get();

        if ($verses->isEmpty()) {
            return $this->notFound();
        }

        $bookMeta = BibleBook::where('translation', $translation)
                             ->where('number', $book)
                             ->first();

        $response = [
            'translation' => $translation,
            'book'        => $book,
            'bookName'    => $bookMeta?->name,
            'bookAbbrev'  => $bookMeta?->abbreviation,
            'testament'   => $bookMeta?->testament,
            'chapter'     => $chapter,
            'totalChapters' => $bookMeta?->chapters_count,
            'verses'      => BibleVerseResource::collection($verses),
        ];

        // Annotations de l'utilisateur (si connecté)
        if ($userId = $request->user()?->id) {
            $base = ['user_id' => $userId, 'translation' => $translation, 'book' => $book, 'chapter' => $chapter];

            $response['annotations'] = [
                'bookmarkedVerses'  => \App\Models\VerseBookmark::where($base)->pluck('tag', 'verse'),
                'highlightedVerses' => \App\Models\VerseHighlight::where($base)->pluck('color', 'verse'),
                'notedVerses'       => \App\Models\VerseNote::where($base)->pluck('note', 'verse'),
            ];
        }

        return $this->success($response);
    }

    // GET /bible/{book}/{chapter}/{verse}?translation=LSG
    public function verse(Request $request, int $book, int $chapter, int $verse): JsonResponse
    {
        $translation = $this->translation($request);

        $v = BibleVerse::where('translation', $translation)
                       ->where('book', $book)
                       ->where('chapter', $chapter)
                       ->where('verse', $verse)
                       ->first();

        if (!$v) {
            return $this->notFound();
        }

        $bookMeta = BibleBook::where('translation', $translation)
                             ->where('number', $book)
                             ->first();

        return $this->success([
            'translation' => $translation,
            'book'        => $book,
            'bookName'    => $bookMeta?->name,
            'bookAbbrev'  => $bookMeta?->abbreviation,
            'chapter'     => $chapter,
            'verse'       => $verse,
            'text'        => $v->text,
            'reference'   => ($bookMeta?->abbreviation ?? "Livre {$book}") . " {$chapter}:{$verse}",
        ]);
    }

    // GET /bible/translations  — liste les traductions installées
    public function translations(): JsonResponse
    {
        $registry = config('bible.translations', []);

        $translations = BibleBook::selectRaw('translation, COUNT(*) as books_count')
                                 ->groupBy('translation')
                                 ->get()
                                 ->map(function ($row) use ($registry) {
                                     $meta        = $registry[$row->translation] ?? [];
                                     $versesCount = BibleVerse::where('translation', $row->translation)->count();
                                     return [
                                         'code'        => $row->translation,
                                         'name'        => $meta['name']      ?? $this->translationName($row->translation),
                                         'language'    => $meta['language']  ?? $this->translationLanguage($row->translation),
                                         'lang_name'   => $meta['lang_name'] ?? null,
                                         'booksCount'  => $row->books_count,
                                         'versesCount' => $versesCount,
                                     ];
                                 });

        return $this->success($translations);
    }

    // GET /bible/download/{translation}  — téléchargement complet pour usage offline
    public function download(string $translation): JsonResponse
    {
        $translation = strtoupper($translation);

        $books = BibleBook::where('translation', $translation)
                          ->orderBy('number')
                          ->get();

        if ($books->isEmpty()) {
            return $this->error("Traduction '{$translation}' non installée.", 404);
        }

        $data = $books->map(function ($book) use ($translation) {
            $chapters = BibleVerse::where('translation', $translation)
                                  ->where('book', $book->number)
                                  ->orderBy('chapter')
                                  ->orderBy('verse')
                                  ->get()
                                  ->groupBy('chapter')
                                  ->map(fn($verses, $chap) => [
                                      'chapter' => (int) $chap,
                                      'verses'  => $verses->map(fn($v) => [
                                          'verse' => $v->verse,
                                          'text'  => $v->text,
                                      ])->values(),
                                  ])
                                  ->values();

            return [
                'number'       => $book->number,
                'name'         => $book->name,
                'abbreviation' => $book->abbreviation,
                'testament'    => $book->testament,
                'chapters'     => $chapters,
            ];
        });

        return $this->success([
            'translation' => $translation,
            'name'        => $this->translationName($translation),
            'language'    => $this->translationLanguage($translation),
            'books'       => $data,
        ]);
    }

    private function translationName(string $code): string
    {
        return config("bible.translations.{$code}.name", $code);
    }

    private function translationLanguage(string $code): string
    {
        return config("bible.translations.{$code}.language", 'unknown');
    }

    // GET /bible/search?q=amour&translation=LSG&book=1&limit=20
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q'     => 'required|string|min:2|max:100',
            'book'  => 'nullable|integer|min:1|max:66',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $translation = $this->translation($request);
        $limit       = min((int) $request->query('limit', 20), 100);

        $query = BibleVerse::where('translation', $translation)
                           ->where('text', 'like', '%' . $request->q . '%');

        if ($book = $request->query('book')) {
            $query->where('book', (int) $book);
        }

        $verses = $query->orderBy('book')->orderBy('chapter')->orderBy('verse')
                        ->limit($limit)
                        ->get();

        // Enrichir chaque verset avec le nom du livre
        $bookNames = BibleBook::where('translation', $translation)
                              ->whereIn('number', $verses->pluck('book')->unique())
                              ->pluck('name', 'number');

        return $this->success([
            'query'       => $request->q,
            'translation' => $translation,
            'count'       => $verses->count(),
            'results'     => $verses->map(fn($v) => [
                'book'      => $v->book,
                'bookName'  => $bookNames[$v->book] ?? null,
                'chapter'   => $v->chapter,
                'verse'     => $v->verse,
                'text'      => $v->text,
                'reference' => ($bookNames[$v->book] ?? "Livre {$v->book}") . " {$v->chapter}:{$v->verse}",
            ]),
        ]);
    }
}

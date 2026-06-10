<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BibleBook;
use App\Models\BibleVerse;
use Illuminate\Http\Request;

class BibleController extends Controller
{
    public function reader(Request $request)
    {
        $translation = strtoupper($request->query('translation', 'LSG'));
        $bookNumber  = (int) $request->query('book', 1);
        $chapter     = (int) $request->query('chapter', 1);

        $translations = BibleBook::selectRaw('translation')
                                 ->groupBy('translation')
                                 ->pluck('translation');

        $books = BibleBook::where('translation', $translation)
                          ->orderBy('number')
                          ->get();

        $currentBook = $books->firstWhere('number', $bookNumber) ?? $books->first();

        $verses = $currentBook
            ? BibleVerse::where('translation', $translation)
                        ->where('book', $currentBook->number)
                        ->where('chapter', $chapter)
                        ->orderBy('verse')
                        ->get()
            : collect();

        // Chapitre précédent / suivant
        $prevChapter = $chapter > 1 ? $chapter - 1 : null;
        $nextChapter = $chapter < ($currentBook?->chapters_count ?? 1) ? $chapter + 1 : null;

        return view('bible.reader', compact(
            'translations', 'translation', 'books',
            'currentBook', 'chapter', 'verses',
            'prevChapter', 'nextChapter'
        ));
    }
}

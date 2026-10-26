<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Support\BibleReference;
use App\Support\ShareMeta;
use Illuminate\Http\Request;

/**
 * Lecture de la Bible sur le site. Liens partageables /bible/{traduction}/{livre}/{chapitre}/{versets},
 * ouverts aussi dans l'application : docs/fonctionnalites/liens-bible.md
 */
class BibleController extends Controller
{
    public function reader(Request $request)
    {
        $translation = strtoupper((string) $request->query('translation', 'LSG'));

        // Ancienne adresse « ?book=43&chapter=3 » : redirigée vers le lien partageable.
        if ($request->filled('book')) {
            $book = BibleReference::bookFromSegment((string) $request->query('book'));
            if ($book) {
                return redirect(BibleReference::passagePath($translation, $book, max(1, (int) $request->query('chapter', 1))), 301);
            }
        }

        return $this->render($translation, 1, 1);
    }

    /** GET /bible/{translation}/{book}/{chapter}/{verses?} */
    public function passage(Request $request, string $translation, string $book, int $chapter, ?string $verses = null)
    {
        $code   = strtoupper($translation);
        $number = BibleReference::bookFromSegment($book);
        $ranges = $verses === null ? [] : BibleReference::parseVerses($verses);
        abort_if(!$number || $chapter < 1 || $ranges === null, 404);

        // Une seule adresse par passage (majuscules, nom du livre, versets triés) : aperçus et partages identiques.
        $canonical = BibleReference::passagePath($code, $number, $chapter, $ranges);
        if (rawurldecode('/' . $request->path()) !== $canonical) {
            return redirect($canonical, 301);
        }

        // Passage absent de cette version (changement de version, lien vers une version retirée) :
        // le plus proche, avec un avertissement, plutôt qu'une page introuvable.
        $chapters = BibleBook::where('translation', $code)->pluck('chapters_count', 'number');
        if (!$chapters->has($number)) {
            $fallback = $this->translationWithBook($number, $code);
            abort_if(!$fallback, 404);
            $message = $chapters->isEmpty()
                ? "La version {$code} n'est pas disponible : passage affiché dans la version {$fallback}."
                : "Ce livre n'existe pas dans la version {$code} : passage affiché dans la version {$fallback}.";

            return redirect(BibleReference::passagePath($fallback, $number, $chapter, $ranges))->with('warning', $message);
        }
        if ($chapter > $chapters[$number]) {
            $last = (int) $chapters[$number];

            return redirect(BibleReference::passagePath($code, $number, $last))
                ->with('warning', "Ce livre compte {$last} chapitres dans la version {$code} : dernier chapitre affiché.");
        }

        return $this->render($code, $number, $chapter, $ranges, true);
    }

    /** Version qui contient ce livre : Louis Segond de préférence, sinon la première trouvée. */
    private function translationWithBook(int $book, string $except): ?string
    {
        $codes = BibleBook::where('number', $book)->where('translation', '!=', $except)->orderBy('translation')->pluck('translation');

        return $codes->contains('LSG') ? 'LSG' : $codes->first();
    }

    private function render(string $translation, int $bookNumber, int $chapter, array $ranges = [], bool $strict = false)
    {
        $translations = BibleBook::selectRaw('translation')
                                 ->groupBy('translation')
                                 ->pluck('translation');

        $books = BibleBook::where('translation', $translation)
                          ->orderBy('number')
                          ->get();

        $currentBook = $books->firstWhere('number', $bookNumber) ?? ($strict ? null : $books->first());
        // Lien partagé vers une traduction, un livre ou un chapitre absent : page introuvable.
        abort_if($strict && (!$currentBook || $chapter > $currentBook->chapters_count), 404);

        $verses = $currentBook
            ? BibleVerse::where('translation', $translation)
                        ->where('book', $currentBook->number)
                        ->where('chapter', $chapter)
                        ->orderBy('verse')
                        ->get()
            : collect();

        // Versets demandés qui existent dans ce chapitre ; aucun : le chapitre entier.
        $selected = array_values(array_intersect(BibleReference::versesIn($ranges), $verses->pluck('verse')->all()));
        if ($ranges && !$selected && $currentBook) {
            return redirect(BibleReference::passagePath($translation, $currentBook->number, $chapter))
                ->with('warning', "Ces versets n'existent pas dans ce chapitre de la version {$translation} : chapitre entier affiché.");
        }

        // Chapitre précédent / suivant
        $prevChapter = $chapter > 1 ? $chapter - 1 : null;
        $nextChapter = $chapter < ($currentBook?->chapters_count ?? 1) ? $chapter + 1 : null;

        $bookName  = $currentBook?->name ?? 'Bible';
        $passage   = BibleReference::passageLabel($bookName, $chapter, $ranges);
        $shareUrl  = $currentBook ? BibleReference::passageUrl($translation, $currentBook->number, $chapter, $ranges) : null;
        $shareText = $selected
            ? $verses->whereIn('verse', $selected)->pluck('text')->implode(' ')
            : $verses->take(3)->pluck('text')->implode(' ');
        $meta = $shareUrl ? ShareMeta::biblePassage("{$passage} ({$translation})", $shareText, $shareUrl) : null;

        return view('bible.reader', compact(
            'translations', 'translation', 'books',
            'currentBook', 'chapter', 'verses',
            'prevChapter', 'nextChapter',
            'ranges', 'selected', 'passage', 'shareUrl', 'shareText', 'meta'
        ));
    }
}

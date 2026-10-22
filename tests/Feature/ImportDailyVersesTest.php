<?php

namespace Tests\Feature;

use App\Models\BibleVerse;
use App\Models\DailyVerse;
use App\Support\BibleReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Commande verse:import. docs/fonctionnalites/verset-du-jour.md */
class ImportDailyVersesTest extends TestCase
{
    use RefreshDatabase;

    private function file(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vdj') . '.json';
        file_put_contents($path, json_encode(['version_texte' => 'Louis Segond', 'versets_du_jour' => $entries], JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function bibleFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bible') . '.json';
        file_put_contents($path, json_encode([
            ['language' => 'French', 'book' => 2, 'chapter' => 20, 'verse' => 16, 'text' => 'Tu ne porteras point de faux témoignage.'],
            ['language' => 'French', 'book' => 65, 'chapter' => 1, 'verse' => 24, 'text' => 'Or, à celui qui peut vous préserver'],
            ['language' => 'French', 'book' => 65, 'chapter' => 1, 'verse' => 25, 'text' => 'Dieu seul, notre Sauveur.'],
        ], JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private const ENTRIES = [
        ['id' => 1, 'theme' => 'Le témoignage de Dieu, sa Loi, l\'arche du témoignage', 'reference' => 'Exode 20:16'],
        ['id' => 2, 'theme' => 'Pierre, Jean, Jude, Apocalypse', 'reference' => 'Jude 24-25'],
    ];

    public function test_imports_texts_themes_and_saves_texts_in_the_file(): void
    {
        $path = $this->file(self::ENTRIES);

        $this->artisan('verse:import', ['file' => $path, '--bible-file' => $this->bibleFile(), '--save-texts' => true])->assertSuccessful();

        $exode = DailyVerse::where('reference', 'Exode 20:16')->first();
        $this->assertSame('Tu ne porteras point de faux témoignage.', $exode->verse_text);
        $this->assertSame('Le témoignage de Dieu, sa Loi, l\'arche du témoignage', $exode->theme);
        $this->assertSame($exode->theme, $exode->themeLabel());
        $this->assertNull($exode->scheduled_date);
        $this->assertSame('Or, à celui qui peut vous préserver Dieu seul, notre Sauveur.', DailyVerse::where('reference', 'Jude 24-25')->value('verse_text'));

        // Le fichier suffit ensuite (import en ligne sans Bible) ; relancer ne crée pas de doublon.
        $saved = json_decode(file_get_contents($path), true)['versets_du_jour'];
        $this->assertSame('Tu ne porteras point de faux témoignage.', $saved[0]['texte']);
        DailyVerse::query()->delete();
        $this->artisan('verse:import', ['file' => $path])->assertSuccessful();
        $this->artisan('verse:import', ['file' => $path])->assertSuccessful();
        $this->assertSame(2, DailyVerse::count());
    }

    public function test_start_schedules_one_verse_per_day_in_file_order_skipping_taken_dates(): void
    {
        DailyVerse::create(['verse_text' => 'Déjà programmé', 'reference' => 'Jean 3:16', 'scheduled_date' => '2026-11-01', 'theme' => 'faithfulness']);

        $this->artisan('verse:import', ['file' => $this->file(self::ENTRIES), '--bible-file' => $this->bibleFile(), '--start' => '2026-11-01'])->assertSuccessful();

        $this->assertSame('2026-11-02', DailyVerse::where('reference', 'Exode 20:16')->first()->scheduled_date->toDateString());
        $this->assertSame('2026-11-03', DailyVerse::where('reference', 'Jude 24-25')->first()->scheduled_date->toDateString());
        $this->assertSame('Fidélité de Dieu', DailyVerse::where('reference', 'Jean 3:16')->first()->themeLabel());
    }

    public function test_texts_from_bible_table_and_errors_stop_the_import(): void
    {
        BibleVerse::create(['translation' => 'LSG', 'book' => 2, 'chapter' => 20, 'verse' => 16, 'text' => 'Texte en base.']);

        // Jude absent de bible_verses : rien n'est écrit.
        $this->artisan('verse:import', ['file' => $this->file(self::ENTRIES)])->assertFailed();
        $this->assertSame(0, DailyVerse::count());

        $this->artisan('verse:import', ['file' => $this->file([self::ENTRIES[0]])])->assertSuccessful();
        $this->assertSame('Texte en base.', DailyVerse::value('verse_text'));
    }

    public function test_single_chapter_books_parse_without_chapter(): void
    {
        $this->assertSame(['book' => 64, 'chapter' => 1, 'verseStart' => 3, 'verseEnd' => 3], BibleReference::parse('3 Jean 3'));
        $this->assertSame(['book' => 65, 'chapter' => 1, 'verseStart' => 24, 'verseEnd' => 25], BibleReference::parse('Jude 24-25'));
        $this->assertNull(BibleReference::parse('Jean 3'));
        $this->assertSame([], BibleReference::findAll('Jude 3 personnes sont venues'));
    }
}

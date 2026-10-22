<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleVerse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Commande bible:import-verses. docs/fonctionnalites/versions-de-la-bible.md */
class ImportBibleVersesTest extends TestCase
{
    use RefreshDatabase;

    private function file(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bible') . '.json';
        file_put_contents($path, "\xEF\xBB\xBF" . json_encode($rows, JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function verse(int $book, int $chapter, int $verse, string $text, string $testament = 'Old'): array
    {
        return ['language' => 'French', 'version' => 'BDS', 'testament' => $testament,
                'book' => $book, 'chapter' => $chapter, 'verse' => $verse, 'text' => $text];
    }

    public function test_imports_books_and_verses(): void
    {
        $path = $this->file([
            $this->verse(1, 1, 1, 'Au commencement '),
            $this->verse(1, 2, 1, 'Ainsi furent achevés'),
            $this->verse(43, 3, 16, 'Oui, Dieu a tant aimé le monde', 'New'),
        ]);

        $this->artisan('bible:import-verses', ['translation' => 'bds', 'file' => $path])->assertSuccessful();

        $genesis = BibleBook::where(['translation' => 'BDS', 'number' => 1])->first();
        $this->assertSame('Genèse', $genesis->name);
        $this->assertSame('Gn', $genesis->abbreviation);
        $this->assertSame('OT', $genesis->testament);
        $this->assertSame(2, $genesis->chapters_count);
        $this->assertSame('NT', BibleBook::where(['translation' => 'BDS', 'number' => 43])->value('testament'));
        $this->assertSame(3, BibleVerse::where('translation', 'BDS')->count());
        $this->assertSame('Au commencement', BibleVerse::where(['translation' => 'BDS', 'book' => 1, 'chapter' => 1, 'verse' => 1])->value('text'));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $path = $this->file([$this->verse(1, 1, 1, 'Au commencement')]);

        $this->artisan('bible:import-verses', ['translation' => 'BDS', 'file' => $path, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, BibleBook::count());
        $this->assertSame(0, BibleVerse::count());
    }

    public function test_rerun_updates_and_fresh_replaces(): void
    {
        $this->artisan('bible:import-verses', ['translation' => 'BDS', 'file' => $this->file([
            $this->verse(1, 1, 1, 'Ancien texte'),
            $this->verse(1, 1, 2, 'Verset retiré ensuite'),
        ])])->assertSuccessful();

        $next = $this->file([$this->verse(1, 1, 1, 'Nouveau texte')]);

        $this->artisan('bible:import-verses', ['translation' => 'BDS', 'file' => $next])->assertSuccessful();
        $this->assertSame(2, BibleVerse::where('translation', 'BDS')->count());
        $this->assertSame('Nouveau texte', BibleVerse::where(['translation' => 'BDS', 'verse' => 1])->value('text'));

        $this->artisan('bible:import-verses', ['translation' => 'BDS', 'file' => $next, '--fresh' => true])->assertSuccessful();
        $this->assertSame(1, BibleVerse::where('translation', 'BDS')->count());
    }

    public function test_english_names_and_names_copied_from_another_translation(): void
    {
        $this->artisan('bible:import-verses', ['translation' => 'NIV', 'file' => $this->file([
            ['language' => 'English', 'book' => 66, 'chapter' => 1, 'verse' => 1, 'text' => 'The revelation'],
        ])])->assertSuccessful();
        $this->assertSame(['Revelation', 'Rev'], array_values(BibleBook::where('translation', 'NIV')->first(['name', 'abbreviation'])->toArray()));

        BibleBook::create(['translation' => 'YOR', 'number' => 66, 'name' => 'Ìfihàn', 'abbreviation' => 'Ifi', 'testament' => 'NT', 'chapters_count' => 22]);

        $this->artisan('bible:import-verses', ['translation' => 'YOR14', 'file' => $this->file([
            ['language' => 'Yoruba', 'book' => 66, 'chapter' => 1, 'verse' => 1, 'text' => 'Ìfihàn Jesu Kristi'],
        ]), '--names-from' => 'yor'])->assertSuccessful();
        $this->assertSame('Ìfihàn', BibleBook::where('translation', 'YOR14')->value('name'));
    }

    public function test_rejects_invalid_content_without_writing(): void
    {
        $path = $this->file([
            $this->verse(1, 1, 1, 'Correct'),
            $this->verse(67, 1, 1, 'Livre inconnu'),
        ]);

        $this->artisan('bible:import-verses', ['translation' => 'BDS', 'file' => $path])->assertFailed();
        $this->assertSame(0, BibleVerse::count());
    }

    public function test_rejects_books_with_chapters_format(): void
    {
        $path = $this->file([['abbrev' => 'gn', 'chapters' => [['Au commencement']]]]);

        $this->artisan('bible:import-verses', ['translation' => 'BDS', 'file' => $path])
            ->expectsOutputToContain('bible:import')
            ->assertFailed();
    }
}

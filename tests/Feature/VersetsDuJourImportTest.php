<?php

namespace Tests\Feature;

use App\Models\DailyVerse;
use App\Models\ThemeSemaine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Import d'un calendrier annuel de versets du jour par semaine thématique (versets:import) et API.
 * Fixture : tests/fixtures/versets_du_jour_test.json (semaines 14 et 15 de 2027, 14 versets).
 * Voir docs/fonctionnalites/verset-du-jour.md
 */
class VersetsDuJourImportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        return base_path('tests/fixtures/versets_du_jour_test.json');
    }

    private function import(array $options = []): void
    {
        $this->artisan('versets:import', ['fichier' => $this->fixture(), ...$options])
            ->expectsOutputToContain('2 semaine(s) et 14 verset(s) importés, du 2027-03-28 au 2027-04-10.')
            ->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_l_import_cree_les_semaines_et_les_versets(): void
    {
        $this->import();

        $this->assertSame(2, ThemeSemaine::count());
        $this->assertSame(14, DailyVerse::count());

        $theme = ThemeSemaine::where('annee', 2027)->where('numero_semaine', 14)->first();
        $this->assertSame('La résurrection de Jésus', $theme->thematique);
        $this->assertSame('Pâques (28 mars)', $theme->periode);
        $this->assertNull(ThemeSemaine::where('numero_semaine', 15)->value('periode'));

        // Apostrophes typographiques et accents gardés tels quels.
        $verse = DailyVerse::pourDate(Carbon::parse('2027-03-28'))->first();
        $this->assertSame('Il n’est point ici; il est ressuscité, comme il l’avait dit.', $verse->verse_text);
        $this->assertSame('Matthieu 28:6', $verse->reference);
        $this->assertSame('LSG 1910', $verse->version);
        $this->assertSame(14, $verse->week_number);
        $this->assertSame($theme->id, $verse->theme_semaine_id);
    }

    public function test_un_second_import_met_a_jour_sans_doublon_et_remplace_le_verset_programme(): void
    {
        // Verset déjà programmé à une date du fichier : remplacé, pas dupliqué.
        $old = DailyVerse::create(['verse_text' => 'Ancien verset', 'reference' => 'Jean 3:16', 'scheduled_date' => '2027-03-29', 'is_active' => true]);

        $this->import();
        $this->import();

        $this->assertSame(2, ThemeSemaine::count());
        $this->assertSame(14, DailyVerse::count());
        $this->assertSame('Psaumes 114:1', $old->fresh()->reference);

        // --fresh : confirmation (réactions et commentaires supprimés avec les versets), puis suppression de
        // l'année et nouvel import. Refuser n'efface rien.
        $question = '--fresh : 14 verset(s) programmé(s) du 2027-03-28 au 2027-04-10 seront supprimés, avec leurs réactions et commentaires. Continuer ?';
        $this->artisan('versets:import', ['fichier' => $this->fixture(), '--fresh' => true])->expectsConfirmation($question, 'no')->assertFailed();
        $this->assertSame($old->id, DailyVerse::whereDate('scheduled_date', '2027-03-29')->value('id'));
        $this->artisan('versets:import', ['fichier' => $this->fixture(), '--fresh' => true])->expectsConfirmation($question, 'yes')->assertSuccessful();
        $this->assertSame(14, DailyVerse::count());
        $this->assertNull($old->fresh());
    }

    public function test_fichier_sur_deux_annees_avec_l_annee_de_chaque_semaine(): void
    {
        // Format de versets_du_jour_2026_2027.json : pas d'« annee » global, une par semaine ; numéros répétés.
        $data = json_decode(file_get_contents($this->fixture()), true);
        unset($data['annee']);
        $data['semaines'][0]['annee'] = 2027;
        $data['semaines'][1] = [...$data['semaines'][0], 'annee' => 2026, 'debut' => '2026-03-29', 'fin' => '2026-04-04',
            'versets' => [[...$data['semaines'][0]['versets'][0], 'date' => '2026-03-29']]];
        $path = tempnam(sys_get_temp_dir(), 'versets') . '.json';
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));

        // Jour passé hors de la période du fichier : jamais touché par --fresh.
        $before = DailyVerse::create(['verse_text' => 'Avant', 'reference' => 'Jean 3:16', 'scheduled_date' => '2026-01-10', 'is_active' => true]);

        $this->artisan('versets:import', ['fichier' => $path, '--fresh' => true])
            ->expectsOutputToContain('2 semaine(s) et 8 verset(s) importés, du 2026-03-29 au 2027-04-03.')->assertSuccessful();
        $this->assertSame(2, ThemeSemaine::where('numero_semaine', 14)->count());
        $this->assertSame(2026, DailyVerse::pourDate(Carbon::parse('2026-03-29'))->first()->themeSemaine->annee);
        $this->assertNotNull($before->fresh());
        @unlink($path);
    }

    public function test_un_fichier_invalide_est_refuse_avec_un_message_clair(): void
    {
        $data = json_decode(file_get_contents($this->fixture()), true);
        $data['semaines'][1]['versets'][0]['date'] = '2027-03-28';   // date en double
        $data['semaines'][0]['versets'][1]['date'] = '2027-02-30';   // date invalide
        unset($data['semaines'][0]['thematique']);
        $path = tempnam(sys_get_temp_dir(), 'versets') . '.json';
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));

        $this->artisan('versets:import', ['fichier' => $path])
            ->expectsOutputToContain('Fichier invalide')
            ->expectsOutputToContain('date en double')
            ->expectsOutputToContain('date invalide')
            ->expectsOutputToContain('clé « thematique » manquante')
            ->assertFailed();
        $this->assertSame(0, DailyVerse::count());

        $this->artisan('versets:import', ['fichier' => 'dailyverse/absent.json'])->expectsOutputToContain('Fichier introuvable')->assertFailed();
        @unlink($path);
    }

    public function test_le_verset_du_jour_suit_le_fuseau_de_porto_novo(): void
    {
        $this->import();

        // 27 mars 23 h 30 UTC = 28 mars 0 h 30 à Porto-Novo (UTC+1) : verset du 28.
        Carbon::setTestNow(Carbon::parse('2027-03-27 23:30:00', 'UTC'));
        $this->getJson('/api/v1/daily-verse')->assertOk()
            ->assertJsonPath('data.date', '2027-03-28')
            ->assertJsonPath('data.reference', 'Matthieu 28:6')
            ->assertJsonPath('data.verseText', 'Il n’est point ici; il est ressuscité, comme il l’avait dit.')
            ->assertJsonPath('data.version', 'LSG 1910')
            ->assertJsonPath('data.dayName', 'Dimanche')
            ->assertJsonPath('data.weekTheme.week', 14)
            ->assertJsonPath('data.weekTheme.theme', 'La résurrection de Jésus')
            ->assertJsonPath('data.weekTheme.keyVerse', 'Matthieu 28:6')
            ->assertJsonPath('data.weekTheme.period', 'Pâques (28 mars)');

        $this->getJson('/api/v1/daily-verse?date=2027-04-04')->assertOk()->assertJsonPath('data.weekTheme.week', 15);
    }

    public function test_repli_sur_le_meme_jour_d_une_autre_annee(): void
    {
        $this->import();

        // 2028-03-29 : pas de verset ce jour-là → celui du 29 mars 2027.
        $this->getJson('/api/v1/daily-verse?date=2028-03-29')->assertOk()
            ->assertJsonPath('data.date', '2027-03-29')
            ->assertJsonPath('data.reference', 'Psaumes 114:1');
        $this->getJson('/api/v1/daily-verse?date=2025-04-10')->assertOk()->assertJsonPath('data.date', '2027-04-10');
    }

    public function test_aucun_verset_renvoie_404(): void
    {
        $this->getJson('/api/v1/daily-verse?date=2027-03-28')->assertNotFound()->assertJsonPath('message', 'Aucun verset programmé.');
        $this->getJson('/api/v1/daily-verse/weeks/2027/14')->assertNotFound();
    }

    public function test_semaine_et_ses_sept_versets(): void
    {
        $this->import();

        $this->getJson('/api/v1/daily-verse/weeks/2027/14')->assertOk()
            ->assertJsonPath('data.theme', 'La résurrection de Jésus')
            ->assertJsonPath('data.startDate', '2027-03-28')
            ->assertJsonPath('data.endDate', '2027-04-03')
            ->assertJsonCount(7, 'data.verses')
            ->assertJsonPath('data.verses.0.dayName', 'Dimanche')
            ->assertJsonPath('data.verses.6.date', '2027-04-03');
        $this->getJson('/api/v1/daily-verse/weeks/2027/60')->assertNotFound();
    }
}

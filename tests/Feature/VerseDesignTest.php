<?php

namespace Tests\Feature;

use App\Models\DailyVerse;
use App\Models\User;
use App\Services\VerseDesignOfTheDay;
use App\Support\VerseColors;
use App\Support\VerseDesigns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Designs du verset du jour : catalogue, design du jour, préférences, design affiché, contraste,
 * image de partage, fuseau Africa/Porto-Novo. Voir docs/fonctionnalites/designs-du-verset.md
 */
class VerseDesignTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER = ['aube', 'nuit', 'ocean', 'lumiere', 'braise', 'minimal', 'vitrail', 'crepuscule', 'matin', 'or', 'encre', 'ardoise'];

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->member = User::create([
            'display_name' => 'Membre', 'email' => 'membre@example.com',
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function verse(string $date): DailyVerse
    {
        return DailyVerse::create([
            'verse_text' => "Car je connais les projets que j'ai formés sur vous, dit l'Éternel, projets de paix et non de malheur.",
            'reference' => 'Jérémie 29 : 11', 'scheduled_date' => $date, 'is_active' => true,
        ]);
    }

    private function deactivate(array $ids): void
    {
        config(['verse_designs.designs' => collect(config('verse_designs.designs'))
            ->map(fn ($d) => in_array($d['id'], $ids, true) ? [...$d, 'active' => false] : $d)->all()]);
    }

    // ─── Design du jour ──────────────────────────────────────────────────────

    public function test_design_of_the_day_matches_the_app_reference_dates(): void
    {
        $this->assertSame('braise', VerseDesignOfTheDay::forDate('2026-10-13'));
        $this->assertSame('or', VerseDesignOfTheDay::forDate('2026-10-14'));
        $this->assertSame('ocean', VerseDesignOfTheDay::forDate('2026-10-15'));
        $this->assertSame('crepuscule', VerseDesignOfTheDay::forDate('2026-10-16'));
        $this->assertSame('aube', VerseDesignOfTheDay::forDate('2024-01-01'));
        // Avant l'origine : index toujours positif.
        $this->assertContains(VerseDesignOfTheDay::forDate('2023-12-31'), self::ORDER);
    }

    public function test_design_of_the_day_is_stable_and_never_repeats_the_next_day(): void
    {
        $this->assertSame(VerseDesignOfTheDay::forDate('2026-03-01'), VerseDesignOfTheDay::forDate('2026-03-01'));
        // Même jour à Porto-Novo, quelle que soit l'heure.
        $this->assertSame(
            VerseDesignOfTheDay::for(Carbon::parse('2026-10-13 00:30', 'Africa/Porto-Novo')),
            VerseDesignOfTheDay::for(Carbon::parse('2026-10-13 23:30', 'Africa/Porto-Novo')),
        );

        $day = Carbon::parse('2026-01-01');
        for ($i = 0; $i < 400; $i++) {
            $today = VerseDesignOfTheDay::forDate($day->toDateString());
            $this->assertNotSame($today, VerseDesignOfTheDay::forDate($day->copy()->addDay()->toDateString()), "{$day->toDateString()}");
            $day->addDay();
        }
    }

    public function test_design_of_the_day_uses_only_active_designs_and_never_repeats_with_ten(): void
    {
        $this->deactivate(['braise', 'encre']);
        $this->assertCount(10, VerseDesigns::activeIds());

        $day = Carbon::parse('2026-10-01');
        $seen = [];
        $previous = null;
        for ($i = 0; $i < 30; $i++) {
            $id = VerseDesignOfTheDay::forDate($day->toDateString());
            $this->assertNotContains($id, ['braise', 'encre']);
            $this->assertNotSame($previous, $id, $day->toDateString());
            $seen[$id] = true;
            $previous = $id;
            $day->addDay();
        }
        $this->assertGreaterThanOrEqual(8, count($seen));
    }

    // ─── Catalogue ───────────────────────────────────────────────────────────

    public function test_catalogue_lists_twelve_designs_in_order_with_defaults(): void
    {
        $res = $this->getJson('/api/v1/verse-designs')->assertOk()
            ->assertJsonCount(12, 'data.designs')
            ->assertJsonPath('data.defaults.font', 'lora')
            ->assertJsonPath('data.defaults.text_size', 'medium')
            ->assertJsonPath('data.defaults.text_align', 'start')
            ->assertJsonPath('data.defaults.design_always', false)
            ->assertJsonPath('data.designs.1.name', 'Nuit étoilée')
            ->assertJsonPath('data.designs.1.tone', 'dark');

        $this->assertSame(self::ORDER, array_column($res->json('data.designs'), 'id'));
        $this->assertSame(['lora', 'sourceSerif', 'inter', 'nunitoSans'], array_column($res->json('data.fonts'), 'id'));
        $this->assertSame([0.86, 1, 1.16], array_column($res->json('data.text_sizes'), 'factor'));
        $this->assertSame(['start', 'center'], array_column($res->json('data.text_aligns'), 'id'));
        $this->assertStringContainsString('max-age=86400', $res->headers->get('Cache-Control'));
    }

    public function test_tone_matches_the_catalogue_for_every_design(): void
    {
        foreach (config('verse_designs.designs') as $d) {
            $this->assertSame($d['tone_light_theme'], VerseDesigns::tone($d['id']), $d['id']);
        }
    }

    // ─── Préférences ─────────────────────────────────────────────────────────

    public function test_style_defaults_then_valid_values_are_saved(): void
    {
        $this->getJson('/api/v1/me/verse-style')->assertUnauthorized();

        $this->actingAs($this->member)->getJson('/api/v1/me/verse-style')->assertOk()
            ->assertJsonPath('data.design_id', null)
            ->assertJsonPath('data.font', 'lora')
            ->assertJsonPath('data.text_size', 'medium');

        $payload = ['design_id' => 'vitrail', 'design_chosen_on' => '2026-10-13', 'design_always' => true,
            'font' => 'sourceSerif', 'text_size' => 'large', 'text_align' => 'center'];
        $this->actingAs($this->member)->putJson('/api/v1/me/verse-style', $payload)->assertOk()
            ->assertJsonPath('data', $payload);
        $this->actingAs($this->member)->getJson('/api/v1/me/verse-style')->assertJsonPath('data', $payload);

        // Mise à jour partielle.
        $this->actingAs($this->member)->putJson('/api/v1/me/verse-style', ['text_size' => 'small'])->assertOk()
            ->assertJsonPath('data.text_size', 'small')
            ->assertJsonPath('data.font', 'sourceSerif');
        $this->assertDatabaseCount('verse_style_preferences', 1);
    }

    public function test_unknown_style_values_are_rejected(): void
    {
        foreach ([
            ['design_id' => 'arc-en-ciel'],
            ['font' => 'comicSans'],
            ['text_size' => 'huge'],
            ['text_align' => 'right'],
            ['design_chosen_on' => '13/10/2026'],
        ] as $bad) {
            $this->actingAs($this->member)->putJson('/api/v1/me/verse-style', $bad)
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($bad));
        }
        $this->assertDatabaseCount('verse_style_preferences', 0);
    }

    public function test_web_route_saves_the_style_with_the_session(): void
    {
        $this->putJson('/verset-du-jour/style', ['font' => 'inter'])->assertUnauthorized();
        $this->actingAs($this->member)->putJson('/verset-du-jour/style', ['font' => 'inter'])->assertOk()
            ->assertJsonPath('data.font', 'inter');
        $this->assertSame('inter', $this->member->fresh()->verseStylePreference->font);
    }

    // ─── Design affiché ──────────────────────────────────────────────────────

    public function test_effective_design_follows_the_app_rules(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-13 10:00', 'Africa/Porto-Novo'));
        $this->verse('2026-10-13');
        $mine = function (array $style) {
            $this->member->verseStylePreference()->updateOrCreate([], $style);
            $this->member->unsetRelation('verseStylePreference'); // même objet d'une requête à l'autre
        };

        // Visiteur : design du jour, pas de préférences.
        $this->getJson('/api/v1/daily-verse')->assertOk()
            ->assertJsonPath('data.design_of_the_day', 'braise')
            ->assertJsonPath('data.effective_design_id', 'braise')
            ->assertJsonPath('data.user_style', null);

        // Toujours : le design choisi, même choisi un autre jour.
        $mine(['design_id' => 'vitrail', 'design_chosen_on' => '2026-09-01', 'design_always' => true]);
        $this->actingAs($this->member)->getJson('/api/v1/daily-verse')
            ->assertJsonPath('data.effective_design_id', 'vitrail')
            ->assertJsonPath('data.user_style.design_id', 'vitrail');

        // Choisi aujourd'hui : le design choisi.
        $mine(['design_id' => 'or', 'design_chosen_on' => '2026-10-13', 'design_always' => false]);
        $this->actingAs($this->member)->getJson('/api/v1/daily-verse')->assertJsonPath('data.effective_design_id', 'or');

        // Choisi hier, sans « toujours » : design du jour.
        $mine(['design_id' => 'or', 'design_chosen_on' => '2026-10-12', 'design_always' => false]);
        $this->actingAs($this->member)->getJson('/api/v1/daily-verse')->assertJsonPath('data.effective_design_id', 'braise');

        // Design choisi désactivé depuis : design du jour.
        $mine(['design_id' => 'or', 'design_always' => true]);
        $this->deactivate(['or']);
        $this->actingAs($this->member)->getJson('/api/v1/daily-verse')->assertJsonPath('data.effective_design_id', VerseDesignOfTheDay::forDate('2026-10-13'));
    }

    // ─── Fuseau horaire ──────────────────────────────────────────────────────

    public function test_verse_and_design_change_at_midnight_in_porto_novo(): void
    {
        $this->verse('2026-10-13');
        $tomorrow = $this->verse('2026-10-14');

        // 23 h 30 UTC = 0 h 30 le lendemain à Porto-Novo (UTC+1).
        Carbon::setTestNow(Carbon::parse('2026-10-13 23:30:00', 'UTC'));
        $this->assertSame($tomorrow->id, DailyVerse::today()->id);
        $this->assertSame('2026-10-14', VerseDesignOfTheDay::todayDate());
        $this->getJson('/api/v1/daily-verse')->assertOk()
            ->assertJsonPath('data.id', $tomorrow->id)
            ->assertJsonPath('data.design_of_the_day', 'or');
    }

    // ─── Contraste ───────────────────────────────────────────────────────────

    public function test_every_design_keeps_text_and_reference_contrast_above_4_5(): void
    {
        $sizes = [[360, 220], [390, 700], [1100, 640], [360, 450]];
        foreach (config('verse_design_colors.designs') as $id => $d) {
            $layers = VerseColors::decodeLayers($d['layers']);
            if ($d['scrim']) {
                $layers[] = ['type' => 'scrim', 'color' => VerseColors::hex($d['scrim']['color']), 'opacity' => $d['scrim']['opacity']];
            }
            $samples = [];
            foreach ($sizes as [$w, $h]) {
                array_push($samples, ...VerseColors::backgroundSamples($layers, $w, $h));
            }
            $this->assertGreaterThanOrEqual(4.5, VerseColors::minContrast(VerseColors::hex($d['ink']), $samples), "{$id} : texte");
            $this->assertGreaterThanOrEqual(4.5, VerseColors::minContrast(VerseColors::hex($d['accent']), $samples), "{$id} : référence");
        }
    }

    public function test_text_size_follows_the_app_interpolation(): void
    {
        $text = fn (int $n) => str_repeat('a', $n);
        $this->assertSame(24.0, VerseDesigns::textSize($text(80), 'card'));
        $this->assertSame(34.0, VerseDesigns::textSize($text(80), 'fullscreen'));
        $this->assertSame(21.0, VerseDesigns::textSize($text(200), 'card'));
        $this->assertSame(28.0, VerseDesigns::textSize($text(200), 'fullscreen'));
        $this->assertSame(18.0, VerseDesigns::textSize($text(320), 'card'));
        $this->assertSame(22.0, VerseDesigns::textSize($text(500), 'fullscreen'));
        $this->assertSame(18.0, VerseDesigns::textSize($text(320), 'card', 'small')); // jamais sous le minimum
        $this->assertSame(27.84, VerseDesigns::textSize($text(80), 'card', 'large'));
        $this->assertSame(24.0, VerseDesigns::textSize('  ' . $text(80) . "\n", 'card')); // espaces ignorés
    }

    // ─── Image de partage et site ────────────────────────────────────────────

    public function test_share_image_is_a_cached_png(): void
    {
        $this->verse('2026-10-13');

        $res = $this->get('/verset-du-jour/2026-10-13/image.png?design=nuit')->assertOk();
        $this->assertSame('image/png', $res->headers->get('Content-Type'));
        Storage::disk('public')->assertExists('verse-images/2026-10-13-nuit.png');
        [$w, $h] = getimagesize(Storage::disk('public')->path('verse-images/2026-10-13-nuit.png'));
        $this->assertSame([1080, 1350], [$w, $h]);

        // Design inconnu : design du jour.
        $this->get('/verset-du-jour/2026-10-13/image.png?design=inconnu')->assertOk();
        Storage::disk('public')->assertExists('verse-images/2026-10-13-braise.png');

        // Verset modifié : images de cette date supprimées.
        DailyVerse::whereDate('scheduled_date', '2026-10-13')->first()->update(['verse_text' => 'Nouveau texte du jour.']);
        Storage::disk('public')->assertMissing('verse-images/2026-10-13-nuit.png');
    }

    public function test_web_page_shows_the_design_and_social_preview(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-13 10:00', 'Africa/Porto-Novo'));
        $this->verse('2026-10-13');

        $this->get('/verset-du-jour')->assertOk()
            ->assertSee('verse-design--braise', false)
            ->assertSee('property="og:image" content="' . route('daily-verse.image', ['date' => '2026-10-13']) . '"', false)
            ->assertSee('twitter:card', false)
            ->assertSee('id="verse-style-panel"', false);

        $this->member->verseStylePreference()->create(['design_id' => 'encre', 'design_always' => true, 'font' => 'inter']);
        $this->actingAs($this->member)->get('/')->assertOk()
            ->assertSee('verse-design--encre', false)
            ->assertSee('verse-font--inter', false);
    }
}

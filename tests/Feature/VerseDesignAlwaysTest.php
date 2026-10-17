<?php

namespace Tests\Feature;

use App\Models\DailyVerse;
use App\Models\User;
use App\Services\VerseDesignOfTheDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verset du jour, « Toujours utiliser mon design » : PUT /me/verse-style enregistre design_always, et
 * GET /me/verse-style comme GET /daily-verse renvoient effective_design_id = le design choisi, quel que
 * soit le jour. Voir docs/fonctionnalites/designs-du-verset.md
 */
class VerseDesignAlwaysTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function at(string $date): void
    {
        Carbon::setTestNow(Carbon::parse("{$date} 10:00", 'Africa/Porto-Novo'));
        DailyVerse::firstOrCreate(['scheduled_date' => $date], [
            'verse_text' => "L'Éternel est mon berger : je ne manquerai de rien.", 'reference' => 'Psaume 23 : 1', 'is_active' => true,
        ]);
    }

    public function test_always_use_my_design_is_saved_and_applies_every_day(): void
    {
        $this->at('2026-10-13');
        Sanctum::actingAs($this->member);

        // Design choisi « toujours », différent du design du jour.
        $chosen = 'vitrail';
        $this->assertNotSame($chosen, VerseDesignOfTheDay::forDate('2026-10-13'));
        $this->putJson('/api/v1/me/verse-style', ['design_id' => $chosen, 'design_chosen_on' => '2026-10-13', 'design_always' => true])
            ->assertOk()
            ->assertJsonPath('data.design_always', true)
            ->assertJsonPath('data.effective_design_id', $chosen);
        $this->assertDatabaseHas('verse_style_preferences', ['user_id' => $this->member->id, 'design_id' => $chosen, 'design_always' => true]);

        // Les jours suivants (design du jour différent) : toujours le design choisi, sur les deux endpoints.
        foreach (['2026-10-14', '2026-10-20', '2027-01-01'] as $day) {
            $this->at($day);
            $ofTheDay = VerseDesignOfTheDay::forDate($day);
            $this->getJson('/api/v1/me/verse-style')->assertOk()
                ->assertJsonPath('data.design_always', true)
                ->assertJsonPath('data.design_of_the_day', $ofTheDay)
                ->assertJsonPath('data.effective_design_id', $chosen);
            $this->getJson('/api/v1/daily-verse')->assertOk()
                ->assertJsonPath('data.user_style.design_always', true)
                ->assertJsonPath('data.effective_design_id', $chosen);
        }

        // Changer seulement une autre préférence ne retire pas « toujours ».
        $this->putJson('/api/v1/me/verse-style', ['font' => 'inter'])->assertOk()
            ->assertJsonPath('data.design_always', true)->assertJsonPath('data.effective_design_id', $chosen);

        // « Toujours » retiré : retour au design du jour (choix fait un autre jour).
        $this->putJson('/api/v1/me/verse-style', ['design_always' => false])->assertOk()
            ->assertJsonPath('data.design_always', false)
            ->assertJsonPath('data.effective_design_id', VerseDesignOfTheDay::forDate('2027-01-01'));
    }

    public function test_design_always_accepts_the_usual_boolean_forms(): void
    {
        $this->at('2026-10-13');
        Sanctum::actingAs($this->member);

        foreach ([1, '1', true] as $value) {
            $this->putJson('/api/v1/me/verse-style', ['design_id' => 'or', 'design_always' => false])->assertOk();
            $this->putJson('/api/v1/me/verse-style', ['design_always' => $value])->assertOk()->assertJsonPath('data.design_always', true);
        }
        $this->putJson('/api/v1/me/verse-style', ['design_always' => 'peut-être'])->assertUnprocessable()->assertJsonValidationErrors('design_always');
    }
}

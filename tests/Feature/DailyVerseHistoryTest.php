<?php

namespace Tests\Feature;

use App\Models\DailyVerse;
use App\Models\DailyVerseComment;
use App\Models\User;
use App\Services\VerseDesignOfTheDay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Historique des versets du jour : GET /api/v1/daily-verse/history et /verset-du-jour/historique.
 * Voir docs/fonctionnalites/verset-du-jour.md
 */
class DailyVerseHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function day(int $daysAgo): string
    {
        return CarbonImmutable::parse(VerseDesignOfTheDay::todayDate())->subDays($daysAgo)->toDateString();
    }

    private function seedVerses(): array
    {
        $today = DailyVerse::create(['verse_text' => 'Car Dieu a tant aimé le monde', 'reference' => 'Jean 3:16',
            'scheduled_date' => $this->day(0), 'is_active' => true, 'like_count' => 4, 'prayer_count' => 2]);
        $yesterday = DailyVerse::create(['verse_text' => "L'Éternel est mon berger", 'reference' => 'Psaume 23:1',
            'scheduled_date' => $this->day(1), 'is_active' => true, 'like_count' => 7]);
        $old = DailyVerse::create(['verse_text' => 'Je puis tout par celui qui me fortifie', 'reference' => 'Philippiens 4:13',
            'scheduled_date' => $this->day(2), 'is_active' => true]);

        return [$today, $yesterday, $old];
    }

    public function test_history_lists_days_newest_first_with_counts(): void
    {
        [$today, $yesterday] = $this->seedVerses();
        $user = User::create(['display_name' => 'Afi', 'email' => 'afi@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active']);
        DailyVerseComment::create(['daily_verse_id' => $yesterday->id, 'user_id' => $user->id, 'body' => 'Amen !']);

        $res = $this->getJson('/api/v1/daily-verse/history?limit=2')->assertOk();

        $res->assertJsonPath('data.0.date', $this->day(0))
            ->assertJsonPath('data.0.id', $today->id)
            ->assertJsonPath('data.0.counts.likes', 4)
            ->assertJsonPath('data.0.counts.prayers', 2)
            ->assertJsonPath('data.1.reference', 'Psaume 23:1')
            ->assertJsonPath('data.1.counts.comments', 1)
            ->assertJsonPath('data.1.myReactions', [])
            ->assertJsonPath('meta.currentPage', 1)
            ->assertJsonPath('meta.lastPage', 2)
            ->assertJsonPath('meta.total', 3);
        $this->assertArrayHasKey('testimonies', $res->json('data.0.counts'));

        $this->getJson('/api/v1/daily-verse/history?limit=2&page=2')->assertOk()
            ->assertJsonPath('data.0.reference', 'Philippiens 4:13');
    }

    public function test_history_shows_my_reactions_when_logged_in(): void
    {
        [$today] = $this->seedVerses();
        $user = User::create(['display_name' => 'Kofi', 'email' => 'kofi@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active']);
        $today->reactions()->create(['user_id' => $user->id, 'type' => 'like']);

        $token = $user->createToken('t')->plainTextToken;
        $this->getJson('/api/v1/daily-verse/history', ['Authorization' => "Bearer {$token}"])->assertOk()
            ->assertJsonPath('data.0.myReactions', ['like']);
    }

    public function test_web_history_page(): void
    {
        $this->seedVerses();
        $this->get('/verset-du-jour/historique')->assertOk()
            ->assertSee('Psaume 23:1')
            ->assertSee(route('daily-verse.show', ['date' => $this->day(1)]), false);
    }

    /** Partage depuis l'historique, sans ouvrir le verset : lien daté (même pour aujourd'hui) et image du jour. */
    public function test_web_history_offers_a_dated_share_link_per_day(): void
    {
        config(['app.share_url' => 'https://temoignage.as-krea.com']);
        $this->seedVerses();

        $this->get('/verset-du-jour/historique')->assertOk()
            ->assertSee('data-share-url="https://temoignage.as-krea.com/verset-du-jour/' . $this->day(1) . '"', false)
            ->assertSee('data-share-url="https://temoignage.as-krea.com/verset-du-jour/' . $this->day(0) . '"', false)
            ->assertSee('data-share-image="' . route('daily-verse.image', ['date' => $this->day(1)]) . '"', false)
            ->assertSee('Copier le lien')->assertSee('Partager');
    }

    /** Comme l'écran de l'application : aujourd'hui mis en avant, compteurs accordés, mes réactions en couleur. */
    public function test_web_history_matches_the_app_screen(): void
    {
        [$today, $yesterday] = $this->seedVerses();
        $user = User::create(['display_name' => 'Kofi', 'email' => 'kofi@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active']);
        $yesterday->reactions()->create(['user_id' => $user->id, 'type' => 'like']);
        DailyVerseComment::create(['daily_verse_id' => $yesterday->id, 'user_id' => $user->id, 'body' => 'Amen !']);

        $this->actingAs($user)->get('/verset-du-jour/historique')->assertOk()
            ->assertSee('Versets précédents')
            ->assertSeeInOrder(["Aujourd'hui", 'Jean 3:16', 'Psaume 23:1', 'Philippiens 4:13'], false)
            ->assertSee('ring-sun-400', false)                 // jour actuel mis en avant
            ->assertSee('4 j&#039;aime', false)->assertSee('2 prières')
            ->assertSee('7 j&#039;aime', false)->assertSee('1 commentaire')
            ->assertSee('Vous aimez ce verset')                 // ma réaction en couleur
            ->assertSee(route('daily-verse.show') . '"', false); // aujourd'hui : verset du jour

        $this->from('/')->post('/langue', ['locale' => 'en']);
        $this->get('/verset-du-jour/historique')->assertOk()->assertSee('Previous verses')->assertSee('Today')->assertSee('1 comment');
    }

    public function test_menu_and_daily_verse_page_lead_to_the_history(): void
    {
        $this->seedVerses();

        // Menu « Découvrir » : verset du jour et versets précédents, comme le menu de l'application.
        $this->get('/')->assertOk()
            ->assertSee(route('daily-verse.show'), false)
            ->assertSee(route('daily-verse.history'), false)
            ->assertSee('Versets précédents');

        // Page du verset du jour : bouton vers l'historique ; menu actif sur l'historique.
        $this->get('/verset-du-jour')->assertOk()->assertSee(route('daily-verse.history'), false);
        $this->get('/verset-du-jour/historique')->assertOk()->assertSee('aria-current="page"', false);

    }
}

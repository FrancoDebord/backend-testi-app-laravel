<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentLocation;
use App\Models\Testimony;
use App\Models\User;
use App\Models\UserLocation;
use App\Services\Locations;
use App\Services\Recommendations;
use App\Support\Countries;
use App\Support\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fils classés par zone puis par goûts (« Pour vous », Mon fil, éléments insérés), localisation
 * privée et effacement. Voir docs/fonctionnalites/localisation.md et recommandations.md
 */
class LocationFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['slug' => 'guerison', 'name' => 'Guérison']);
        Category::create(['slug' => 'famille', 'name' => 'Famille']);
        config(['recommendations.geocoder.enabled' => false]);
    }

    private function user(string $name, ?string $country = null, ?string $city = null, array $attrs = []): User
    {
        $user = User::create(array_merge([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active', 'country' => $country,
        ], $attrs));
        if ($city) app(Locations::class)->setHomeCity($user, $city);

        return $user;
    }

    private function testimony(User $author, string $title, string $category = 'guerison', array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $author->id, 'title' => $title, 'type' => 'video', 'category_slug' => $category,
            'media_url' => 'https://example.com/v.mp4', 'visibility' => 'public', 'status' => 'approved',
            'approved_at' => now()->subDays(40), 'views_count' => 10,
        ], $attrs));
    }

    /** Quatre auteurs à des distances croissantes de Cotonou (Bénin). */
    private function zones(): array
    {
        return [
            'monde'    => $this->testimony($this->user('Paris', 'France', 'Paris'), 'Autre continent'),
            'continent'=> $this->testimony($this->user('Lomé', 'Togo', 'Lomé'), 'Même continent'),
            'pays'     => $this->testimony($this->user('Parakou', 'Bénin', 'Parakou'), 'Même pays'),
            'ville'    => $this->testimony($this->user('Cotonou', 'Bénin', 'Cotonou'), 'Même ville'),
        ];
    }

    public function test_place_levels_and_phrases(): void
    {
        $cotonou = Place::make(6.37, 2.39, 'Akpakpa', 'Cotonou', 'Littoral', 'bj');
        $this->assertSame('AF', $cotonou->continent);
        $this->assertSame(Place::LEVEL_NEAR, $cotonou->levelTo(Place::make(6.36, 2.42, null, 'Cotonou', null, 'bj')));
        $this->assertSame(Place::LEVEL_CITY, $cotonou->levelTo(Place::make(city: 'cotonou', countryCode: 'bj')));
        $this->assertSame(Place::LEVEL_CITY, $cotonou->levelTo(Place::make(6.50, 2.60, countryCode: 'bj'))); // ≈ 27 km (Porto-Novo)
        $this->assertSame(Place::LEVEL_COUNTRY, $cotonou->levelTo(Place::make(city: 'Parakou', countryCode: 'bj')));
        $this->assertSame(Place::LEVEL_CONTINENT, $cotonou->levelTo(Place::make(countryCode: 'tg')));
        $this->assertSame(Place::LEVEL_WORLD, $cotonou->levelTo(Place::make(countryCode: 'fr')));
        $this->assertSame(Place::LEVEL_WORLD, $cotonou->levelTo(null));
        $this->assertSame(6.37, Place::make(6.36789, 2.4)->latitude);

        $this->assertSame('Au Bénin', Countries::inPhrase('bj'));
        $this->assertSame("En Côte d'Ivoire", Countries::inPhrase('ci'));
        $this->assertSame('À Madagascar', Countries::inPhrase('mg'));
        $this->assertSame('Aux Pays-Bas', Countries::inPhrase('nl'));
        $this->assertSame('bj', Locations::countryFromLanguage('fr-BJ,fr;q=0.9'));
        $this->assertNull(Locations::countryFromLanguage('fr'));
    }

    public function test_for_you_orders_same_city_then_country_then_continent_at_equal_tastes(): void
    {
        $z = $this->zones();
        $me = $this->user('Moi', 'Bénin', 'Cotonou');

        $ids = app(Recommendations::class)->feed($me, 1, 4)->pluck('id')->all();
        $this->assertSame([$z['ville']->id, $z['pays']->id, $z['continent']->id, $z['monde']->id], $ids);

        // API : mention en tête et étiquettes discrètes des cartes proches.
        $this->actingAs($me)->getJson('/api/v1/testimonies?sort=for_you&limit=4')->assertOk()
            ->assertJsonPath('data.0.id', $z['ville']->id)
            ->assertJsonPath('meta.place.label', 'Cotonou')
            ->assertJsonPath('meta.place.source', 'profile')
            ->assertJsonPath("meta.nearby.{$z['ville']->id}", 'À Cotonou')
            ->assertJsonPath("meta.nearby.{$z['pays']->id}", 'Au Bénin')
            ->assertJsonMissingPath("meta.nearby.{$z['monde']->id}");
    }

    public function test_tastes_break_ties_inside_a_zone(): void
    {
        $neighbour = $this->user('Voisin', 'Bénin', 'Cotonou');
        $famille = $this->testimony($neighbour, 'Famille à Cotonou', 'famille');
        $guerison = $this->testimony($neighbour, 'Guérison à Cotonou', 'guerison');
        $far = $this->testimony($this->user('Loin', 'France', 'Paris'), 'Guérison à Paris', 'guerison');
        $liked = $this->testimony($this->user('Ami', 'France', 'Lyon'), 'Guérison aimée', 'guerison', ['approved_at' => now()->subYear()]);
        $me = $this->user('Moi', 'Bénin', 'Cotonou');
        DB::table('reactions')->insert(['id' => (string) str()->uuid(), 'user_id' => $me->id, 'testimony_id' => $liked->id,
            'type' => 'like', 'created_at' => now(), 'updated_at' => now()]);

        $ids = app(Recommendations::class)->feed($me, 1, 4)->pluck('id')->all();
        // Même zone : la catégorie aimée d'abord ; la zone reste prioritaire sur les goûts.
        $this->assertSame([$guerison->id, $famille->id], array_slice($ids, 0, 2));
        $this->assertContains($far->id, $ids);
    }

    public function test_personal_feed_keeps_followed_accounts_first_then_zones(): void
    {
        $z = $this->zones();
        $me = $this->user('Moi', 'Bénin', 'Cotonou');
        $friendFar = $this->user('Ami lointain', 'France', 'Lyon');
        $friendNear = $this->user('Ami proche', 'Bénin', 'Cotonou');
        $me->following()->attach([$friendFar->id, $friendNear->id]);
        $farPost = $this->testimony($friendFar, 'Suivi lointain', 'guerison', ['approved_at' => now()->subHour()]);
        $nearPost = $this->testimony($friendNear, 'Suivi proche', 'guerison', ['approved_at' => now()->subDays(3)]);

        $ids = $this->actingAs($me)->getJson('/api/v1/feed?limit=10')->assertOk()
            ->assertJsonPath('meta.place.label', 'Cotonou')
            ->json('data.*.id');

        // Comptes suivis d'abord (le plus proche avant le plus récent), puis les autres par zone.
        $this->assertSame([$nearPost->id, $farPost->id], array_slice($ids, 0, 2));
        $this->assertSame([$z['ville']->id, $z['pays']->id, $z['continent']->id, $z['monde']->id], array_slice($ids, 2, 4));
    }

    public function test_guest_uses_browser_language_country(): void
    {
        $z = $this->zones();

        $ids = $this->getJson('/api/v1/testimonies?sort=for_you&limit=4', ['Accept-Language' => 'fr-BJ,fr;q=0.9'])
            ->assertOk()->assertJsonPath('meta.place.source', 'language')->json('data.*.id');
        // Pays seulement : les deux témoignages du Bénin d'abord (même niveau), puis le Togo, puis la France.
        $this->assertEqualsCanonicalizing([$z['ville']->id, $z['pays']->id], array_slice($ids, 0, 2));
        $this->assertSame([$z['continent']->id, $z['monde']->id], array_slice($ids, 2, 2));
    }

    public function test_gps_position_is_rounded_geocoded_private_and_erasable(): void
    {
        config(['recommendations.geocoder.enabled' => true]);
        Http::fake(['*' => Http::response(['address' => [
            'suburb' => 'Akpakpa', 'city' => 'Cotonou', 'state' => 'Littoral', 'country_code' => 'bj',
        ]])]);
        $me = $this->user('Moi', 'Bénin');

        $this->actingAs($me)->putJson('/api/v1/users/me/location', ['latitude' => 6.367891, 'longitude' => 2.433456])
            ->assertOk()
            ->assertJsonPath('data.useGps', true)
            ->assertJsonPath('data.source', 'gps')
            ->assertJsonPath('data.label', 'Cotonou')
            ->assertJsonPath('data.locality', 'Akpakpa')
            ->assertJsonPath('data.latitude', 6.37);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'lat=6.37') && $r->hasHeader('User-Agent'));
        $this->assertSame(6.37, UserLocation::find($me->id)->latitude);

        // Un témoignage publié ensuite reçoit une copie grossière du lieu.
        $post = $this->testimony($me, 'Publié à Cotonou');
        $this->assertDatabaseHas('content_locations', ['subject_id' => $post->id, 'city' => 'Cotonou', 'source' => 'gps', 'continent' => 'AF']);

        // Jamais dans les ressources publiques.
        $other = $this->user('Autre', 'France');
        $profile = $this->actingAs($other)->getJson("/api/v1/users/{$me->id}")->assertOk()->getContent();
        $show = $this->getJson("/api/v1/testimonies/{$post->id}")->assertOk()->getContent();
        foreach ([$profile, $show] as $json) {
            $this->assertStringNotContainsString('Akpakpa', $json);
            $this->assertStringNotContainsString('6.37', $json);
            $this->assertStringNotContainsString('homeCity', $json);
        }

        // Effacement immédiat : position et copies GPS des contenus.
        $this->actingAs($me)->deleteJson('/api/v1/users/me/location')->assertOk()
            ->assertJsonPath('data.useGps', false)->assertJsonPath('data.source', 'profile')
            ->assertJsonPath('data.latitude', null);
        $this->assertNull(UserLocation::find($me->id));
        $this->assertDatabaseMissing('content_locations', ['subject_id' => $post->id]);
    }

    public function test_turning_gps_off_erases_coordinates(): void
    {
        $me = $this->user('Moi', 'Bénin');
        $this->actingAs($me)->putJson('/api/v1/users/me/location', [
            'latitude' => 6.37, 'longitude' => 2.43, 'city' => 'Cotonou', 'country_code' => 'bj',
        ])->assertOk()->assertJsonPath('data.label', 'Cotonou'); // noms de l'appareil (géocodeur désactivé)

        $this->actingAs($me)->putJson('/api/v1/users/me/location', ['use_gps' => false, 'home_city' => 'Porto-Novo'])
            ->assertOk()->assertJsonPath('data.useGps', false)->assertJsonPath('data.label', 'Porto-Novo')
            ->assertJsonPath('data.latitude', null);
        $this->assertNull(UserLocation::find($me->id)->latitude);
    }

    public function test_feed_inserts_put_nearest_events_first(): void
    {
        $org = fn (string $name) => $this->user($name, 'Bénin', null, [
            'account_type' => 'organization', 'organization_name' => $name,
            'organization_type' => 'church', 'verification_status' => 'verified',
        ]);
        $event = fn (User $by, string $title, string $city, string $country, int $inDays) => $this->actingAs($by)->postJson('/api/v1/events', [
            'title' => $title, 'type' => 'crusade', 'city' => $city, 'country' => $country,
            'starts_at' => now()->addDays($inDays)->toIso8601String(), 'ends_at' => now()->addDays($inDays + 1)->toIso8601String(),
        ])->assertCreated();
        $event($org('Église A'), 'Croisade à Paris', 'Paris', 'France', 2);
        $event($org('Église B'), 'Croisade à Lomé', 'Lomé', 'Togo', 3);
        $event($org('Église C'), 'Croisade à Cotonou', 'Cotonou', 'Bénin', 9);
        $me = $this->user('Moi', 'Bénin', 'Cotonou');

        $titles = $this->actingAs($me)->getJson('/api/v1/feed/inserts')->assertOk()->json('data.others.events.*.title');
        $this->assertSame(['Croisade à Cotonou', 'Croisade à Lomé', 'Croisade à Paris'], $titles);
    }

    public function test_deleting_the_account_erases_the_location(): void
    {
        $me = $this->user('Moi', 'Bénin', 'Cotonou');
        $this->assertNotNull(UserLocation::find($me->id));
        $me->delete(); // suppression douce (comme DELETE /users/me)
        $this->assertNull(UserLocation::find($me->id));
    }

    public function test_backfill_command_is_idempotent(): void
    {
        $author = $this->user('Auteur', 'Bénin', 'Cotonou');
        $post = $this->testimony($author, 'Ancien');
        ContentLocation::query()->delete();

        $this->artisan('locations:backfill')->assertSuccessful();
        $this->assertDatabaseHas('content_locations', ['subject_id' => $post->id, 'city' => 'Cotonou', 'country_code' => 'bj', 'source' => 'profile']);
        $this->artisan('locations:backfill')->assertSuccessful();
        $this->assertSame(1, ContentLocation::where('subject_id', $post->id)->count());
    }

    public function test_web_settings_and_personal_feed_show_location(): void
    {
        $me = $this->user('Moi', 'Bénin', 'Cotonou');
        $this->actingAs($me)->get('/profile/settings')->assertOk()
            ->assertSee('Utiliser ma position pour personnaliser mes fils')->assertSee('Cotonou');
        $this->actingAs($me)->get('/mon-fil')->assertOk()
            ->assertSee('Près de chez vous · Cotonou')
            ->assertSee('Activez votre position pour des témoignages proches de vous');

        $this->actingAs($me)->put('/profile/localisation', ['use_gps' => '0', 'home_city' => 'Ouidah'])
            ->assertRedirect();
        $this->assertSame('Ouidah', UserLocation::find($me->id)->home_city);
        $this->actingAs($me)->delete('/profile/localisation')->assertRedirect();
        $this->assertNull(UserLocation::find($me->id));
    }
}

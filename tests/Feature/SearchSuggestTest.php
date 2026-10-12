<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\Category;
use App\Models\Event;
use App\Models\Testimony;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\SearchSuggestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Autocomplétion des barres de recherche. docs/fonctionnalites/recherche.md */
class SearchSuggestTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function user(string $name, array $attrs = []): User
    {
        $n = ++self::$seq;

        return User::create(array_merge([
            'display_name' => $name, 'email' => "personne{$n}@example.com", 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active', 'phone' => '+2299' . str_pad((string) $n, 7, '0', STR_PAD_LEFT),
        ], $attrs));
    }

    private function testimony(User $author, string $title, array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $author->id, 'title' => $title, 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ], $attrs));
    }

    private function suggest(string $q, string $scope = 'all'): array
    {
        Cache::flush();

        return $this->getJson('/api/v1/search/suggest?' . http_build_query(['q' => $q, 'scope' => $scope]))
            ->assertOk()->assertJsonPath('success', true)->json('data');
    }

    private function texts(array $data): array
    {
        return array_column($data['suggestions'], 'text');
    }

    public function test_prefix_completion_is_accent_and_case_insensitive(): void
    {
        $author = $this->user('Marie Kossou');
        $this->testimony($author, 'Guérison miraculeuse de ma mère');
        $this->testimony($author, 'Dieu a guéri mon fils du paludisme');

        // Saisie sans accent et en majuscules : « GUER » → « Guérison miraculeuse… » d'abord (début de texte).
        $data = $this->suggest('GUER');
        $texts = $this->texts($data);
        $this->assertSame('Guérison miraculeuse de ma mère', $texts[0]);
        $this->assertContains('guéri mon fils', $texts); // début de mot au milieu d'un titre
        $this->assertSame([0, 4], $data['suggestions'][0]['highlight']);

        // Résultat direct typé, ouvert dans l'application ou sur le site.
        $first = collect($data['results'])->firstWhere('type', 'testimony');
        $this->assertNotNull($first);
        $this->assertStringStartsWith('/testimony/', $first['route']);
        $this->assertStringContainsString('/testimonies/', $first['url']);
    }

    public function test_short_query_returns_nothing(): void
    {
        $this->testimony($this->user('Paul'), 'Guérison');
        $data = $this->suggest('g');
        $this->assertSame([], $data['suggestions']);
        $this->assertSame([], $data['results']);
    }

    public function test_users_categories_events_and_bible(): void
    {
        $org = $this->user('Église Évangélique de Cotonou', ['account_type' => 'organization', 'organization_name' => 'Église Évangélique de Cotonou']);
        Category::create(['name' => 'Délivrance', 'slug' => 'delivrance', 'is_active' => true, 'display_order' => 1]);
        Event::create([
            'organizer_id' => $org->id, 'title' => 'Croisade de délivrance', 'type' => 'crusade',
            'status' => 'published', 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(4),
        ]);
        BibleBook::create(['translation' => 'LSG', 'number' => 5, 'name' => 'Deutéronome', 'abbreviation' => 'Dt', 'testament' => 'AT', 'chapters_count' => 34]);

        $users = $this->suggest('eglise', 'users');
        $this->assertSame('Église Évangélique de Cotonou', $users['results'][0]['label']);
        $this->assertSame('user', $users['results'][0]['type']);
        $this->assertSame('/users/' . $org->id, $users['results'][0]['route']);

        $all = $this->suggest('deli');
        $types = array_column($all['results'], 'type');
        $this->assertContains('category', $types);
        $this->assertContains('event', $types);
        $this->assertContains('Délivrance', $this->texts($all));

        $bible = $this->suggest('deut', 'bible');
        $this->assertSame('Deutéronome', $bible['results'][0]['label']);
        $this->assertSame(5, $bible['results'][0]['book']);
        $this->assertSame('/bible/LSG?book=5', $bible['results'][0]['route']);
        // La portée « bible » ne mélange pas d'autres sources.
        $this->assertSame(['bible'], array_values(array_unique(array_column($bible['results'], 'type'))));
    }

    public function test_hidden_content_never_leaks(): void
    {
        $active = $this->user('Samuel Actif');
        $suspended = $this->user('Samuel Suspendu', ['status' => 'suspended']);
        $private = $this->user('Samuel Privé');
        UserSetting::create(['user_id' => $private->id, 'is_private_account' => true]);

        $this->testimony($active, 'Samuel et la prière exaucée');
        $this->testimony($active, 'Samuel journal secret', ['visibility' => 'private', 'status' => 'approved']);
        $this->testimony($active, 'Samuel en attente', ['status' => 'pending', 'approved_at' => null]);
        $this->testimony($suspended, 'Samuel témoignage de suspendu');

        $data = $this->suggest('samuel');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Samuel Actif', $json);
        $this->assertStringContainsString('Samuel et la prière exaucée', $json);
        foreach (['Suspendu', 'Privé', 'journal secret', 'en attente', 'suspendu'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $json);
        }
        // Jamais d'e-mail ni de téléphone.
        $this->assertStringNotContainsString('@example.com', $json);
        $this->assertStringNotContainsString('+2299', $json);
    }

    public function test_suspension_is_applied_even_with_a_warm_lexicon(): void
    {
        $user = $this->user('Rachel Témoin');
        Cache::flush();
        $this->getJson('/api/v1/search/suggest?q=rachel')->assertOk()->assertJsonPath('data.results.0.label', 'Rachel Témoin');

        $user->update(['status' => 'banned']);
        // Le lexique (10 min) contient encore le nom ; seul le cache de 60 s par préfixe est vidé.
        Cache::forget('search:suggest:v1:all:8:' . md5('rachel'));
        $json = json_encode($this->getJson('/api/v1/search/suggest?q=rachel')->json('data'));
        $this->assertStringNotContainsString('Rachel', $json);
    }

    public function test_popular_searches_are_anonymous_and_need_several_hits(): void
    {
        $this->postJson('/api/v1/search/log', ['q' => 'Jeûne et prière'])->assertOk();
        $this->postJson('/api/v1/search/log', ['q' => 'jeune et priere'])->assertOk();
        $this->assertNotContains('jeûne et prière', $this->texts($this->suggest('jeu')));

        $this->postJson('/api/v1/search/log', ['q' => 'jeûne et prière'])->assertOk();
        $row = DB::table('search_queries')->first();
        $this->assertSame(3, (int) $row->hits);
        $this->assertSame(['id', 'normalized', 'query', 'hits', 'last_searched_at', 'created_at', 'updated_at'], array_keys((array) $row));
        $this->assertContains('jeûne et prière', $this->texts($this->suggest('jeu')));

        // E-mail ou numéro : jamais enregistrés.
        $this->postJson('/api/v1/search/log', ['q' => 'quelqu.un@example.com'])->assertOk();
        $this->postJson('/api/v1/search/log', ['q' => '+229 97 00 00 00'])->assertOk();
        $this->assertSame(1, DB::table('search_queries')->count());
    }

    public function test_guest_access_and_validation(): void
    {
        $this->getJson('/api/v1/search/suggest?q=ab&scope=nimporte')->assertStatus(422);
        $this->getJson('/api/v1/search/suggest?q=ab&limit=99')->assertStatus(422);
        $this->getJson('/api/v1/search/suggest')->assertOk()->assertJsonPath('data.suggestions', []);
    }

    public function test_site_search_bars_are_wired_and_validated_searches_recorded(): void
    {
        $html = $this->get('/explore?q=' . urlencode('Délivrance totale'))->assertOk()->getContent();
        $this->assertStringContainsString('data-search-autocomplete="all"', $html);       // en-tête + page Explorer
        $this->assertStringContainsString("setAttribute('role', 'combobox')", $html);         // script inclus
        $this->assertSame(1, substr_count($html, 'const SUGGEST_URL'));                     // script inclus une seule fois
        $this->assertStringContainsString('api\/v1\/search\/suggest', $html);
        $this->assertSame('délivrance totale', DB::table('search_queries')->value('query'));
    }

    public function test_fold_keeps_offsets_of_the_original_text(): void
    {
        [$norm, $map] = SearchSuggestions::fold('Cœur brisé, ÉGLISE !');
        $this->assertSame('coeur brise eglise', $norm);
        $this->assertSame([12, 6], SearchSuggestions::highlight('Cœur brisé, ÉGLISE !', 'eglise'));
        $this->assertSame([0, 4], SearchSuggestions::highlight('Cœur brisé', 'coeur'));
        $this->assertCount(strlen($norm) + 1, $map);
    }
}

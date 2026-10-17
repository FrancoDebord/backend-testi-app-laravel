<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Direct YouTube d'un événement : lien collé par l'organisateur (API et site), identifiant
 * enregistré, champ `youtubeLive` de l'API, lecteur intégré sur la page de l'événement.
 * Voir docs/fonctionnalites/evenements.md › Direct YouTube
 */
class EventYouTubeLiveTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'dQw4w9WgXcQ';

    private User $org;  // organisation vérifiée
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = User::create([
            'display_name' => 'Org', 'email' => 'org@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
            'account_type' => 'organization', 'organization_name' => 'Église de la Grâce',
            'organization_type' => 'church', 'verification_status' => 'verified',
        ]);
        $this->user = User::create([
            'display_name' => 'Fidèle', 'email' => 'fidele@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'title'     => 'Grande croisade de Cotonou',
            'type'      => 'crusade',
            'starts_at' => now()->addDays(10)->toIso8601String(),
            'ends_at'   => now()->addDays(12)->toIso8601String(),
        ], $extra);
    }

    public function test_create_with_youtube_link_returns_youtube_live(): void
    {
        $res = $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload([
            'youtube_live_url' => 'https://www.youtube.com/live/' . self::ID . '?si=abc',
        ]))->assertCreated();

        $res->assertJsonPath('data.youtubeLive', [
            'id'           => self::ID,
            'url'          => 'https://www.youtube.com/watch?v=' . self::ID,
            'embedUrl'     => 'https://www.youtube-nocookie.com/embed/' . self::ID . '?rel=0&modestbranding=1&playsinline=1',
            'thumbnailUrl' => 'https://i.ytimg.com/vi/' . self::ID . '/hqdefault.jpg',
        ]);
        $res->assertJsonPath('data.live', null); // le direct LiveKit reste un champ distinct
        $this->assertSame(self::ID, Event::findOrFail($res->json('data.id'))->youtube_live_id);
    }

    public function test_link_formats_are_accepted(): void
    {
        foreach ([
            'https://youtu.be/' . self::ID,
            'https://www.youtube.com/watch?v=' . self::ID . '&t=10',
            'https://m.youtube.com/shorts/' . self::ID,
            'https://www.youtube.com/embed/' . self::ID,
            self::ID,
        ] as $link) {
            $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload(['youtube_live_url' => $link]))
                ->assertCreated()->assertJsonPath('data.youtubeLive.id', self::ID);
        }
    }

    public function test_without_link_youtube_live_is_null(): void
    {
        $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload())
            ->assertCreated()->assertJsonPath('data.youtubeLive', null);
    }

    public function test_update_sets_keeps_and_clears(): void
    {
        $id = $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload())->json('data.id');

        $this->putJson("/api/v1/events/{$id}", ['youtube_live_url' => 'youtu.be/' . self::ID])
            ->assertOk()->assertJsonPath('data.youtubeLive.id', self::ID);

        // Champ absent : inchangé.
        $this->putJson("/api/v1/events/{$id}", ['title' => 'Croisade 2026'])
            ->assertOk()->assertJsonPath('data.youtubeLive.id', self::ID);

        // Chaîne vide : retiré.
        $this->putJson("/api/v1/events/{$id}", ['youtube_live_url' => ''])
            ->assertOk()->assertJsonPath('data.youtubeLive', null);
        $this->assertNull(Event::findOrFail($id)->youtube_live_id);

        // null : retiré aussi.
        $this->putJson("/api/v1/events/{$id}", ['youtube_live_url' => self::ID])->assertOk();
        $this->putJson("/api/v1/events/{$id}", ['youtube_live_url' => null])
            ->assertOk()->assertJsonPath('data.youtubeLive', null);
    }

    public function test_invalid_link_is_rejected(): void
    {
        $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload([
            'youtube_live_url' => 'https://vimeo.com/123456',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['youtube_live_url' => __('events.validation.youtube_live_invalid')]);

        $id = $this->postJson('/api/v1/events', $this->payload(['youtube_live_url' => self::ID]))->json('data.id');
        $this->putJson("/api/v1/events/{$id}", ['youtube_live_url' => 'https://www.youtube.com/watch?v=court'])
            ->assertStatus(422)->assertJsonValidationErrors('youtube_live_url');
        $this->assertSame(self::ID, Event::findOrFail($id)->youtube_live_id);
    }

    public function test_only_managers_can_set_it(): void
    {
        $id = $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload())->json('data.id');

        $this->actingAs($this->user)->putJson("/api/v1/events/{$id}", ['youtube_live_url' => self::ID])->assertForbidden();
        $this->assertNull(Event::findOrFail($id)->youtube_live_id);
    }

    public function test_web_form_and_show_page_embed(): void
    {
        $id = $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload())->json('data.id');
        $this->app['auth']->forgetGuards();

        // Formulaire du site : enregistrement du lien.
        $this->actingAs($this->org)->put(route('events.update', $id), [
            ...$this->payload(),
            'status'           => 'published',
            'youtube_live_url' => 'https://youtu.be/' . self::ID,
        ])->assertRedirect(route('events.show', $id));
        $this->assertSame(self::ID, Event::findOrFail($id)->youtube_live_id);

        $this->get(route('events.edit', $id))->assertOk()
            ->assertSee('name="youtube_live_url"', false)
            ->assertSee('https://www.youtube.com/watch?v=' . self::ID, false);

        $this->get(route('events.show', $id))->assertOk()
            ->assertSee(__('events.show.youtube_live_title'))
            ->assertSee('https://www.youtube-nocookie.com/embed/' . self::ID, false)
            ->assertSee(__('events.show.youtube_live_open'));

        // Champ vidé : bloc retiré.
        $this->put(route('events.update', $id), [...$this->payload(), 'status' => 'published', 'youtube_live_url' => ''])
            ->assertRedirect(route('events.show', $id));
        $this->get(route('events.show', $id))->assertOk()
            ->assertDontSee('youtube-nocookie.com/embed/', false);
    }

    public function test_web_form_rejects_invalid_link(): void
    {
        $id = $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload())->json('data.id');
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->org)->from(route('events.edit', $id))->put(route('events.update', $id), [
            ...$this->payload(), 'status' => 'published', 'youtube_live_url' => 'pas un lien',
        ])->assertRedirect(route('events.edit', $id))
            ->assertSessionHasErrors(['youtube_live_url' => __('events.validation.youtube_live_invalid')]);
    }
}

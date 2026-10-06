<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\LiveSession;
use App\Models\Testimony;
use App\Models\User;
use App\Services\LiveKit\LiveKitClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Événements chrétiens : droits de création, gestion, participation, commentaires,
 * témoignages officiels et direct de l'événement. Voir docs/fonctionnalites/evenements.md
 */
class EventTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $org;        // organisation vérifiée
    private User $pendingOrg; // organisation non vérifiée
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser('admin', ['role' => 'administrateur']);
        $this->org = $this->makeUser('org', [
            'account_type' => 'organization', 'organization_name' => 'Église de la Grâce',
            'organization_type' => 'church', 'verification_status' => 'verified',
        ]);
        $this->pendingOrg = $this->makeUser('pending', [
            'account_type' => 'organization', 'organization_name' => 'Ministère Lumière',
            'organization_type' => 'ministry', 'verification_status' => 'pending',
        ]);
        $this->user = $this->makeUser('fidele');
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'title'     => 'Grande croisade de Cotonou',
            'type'      => 'crusade',
            'starts_at' => now()->addDays(10)->toIso8601String(),
            'ends_at'   => now()->addDays(12)->toIso8601String(),
            'city'      => 'Cotonou',
            'guests'    => [['name' => 'Pasteur Jean', 'role' => 'Orateur principal']],
        ], $extra);
    }

    private function createEvent(?User $by = null, array $extra = []): Event
    {
        $id = $this->actingAs($by ?? $this->org)->postJson('/api/v1/events', $this->payload($extra))
            ->assertCreated()->json('data.id');

        return Event::findOrFail($id);
    }

    private function guest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    // ─── Création et droits ──────────────────────────────────────────────────

    public function test_verified_organization_and_admin_can_create(): void
    {
        $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.type', 'crusade')
            ->assertJsonPath('data.phase', 'upcoming')
            ->assertJsonPath('data.canManage', true)
            ->assertJsonPath('data.organizer.displayName', 'Église de la Grâce')
            ->assertJsonPath('data.organizer.isVerified', true)
            ->assertJsonPath('data.guests.0.name', 'Pasteur Jean');

        $this->actingAs($this->admin)->postJson('/api/v1/events', $this->payload())->assertCreated();
    }

    public function test_others_cannot_create(): void
    {
        $this->actingAs($this->pendingOrg)->postJson('/api/v1/events', $this->payload())->assertForbidden();
        $this->actingAs($this->user)->postJson('/api/v1/events', $this->payload())->assertForbidden();
        $this->guest()->postJson('/api/v1/events', $this->payload())->assertUnauthorized();
    }

    public function test_validation_end_after_start(): void
    {
        $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload([
            'starts_at' => now()->addDays(5)->toIso8601String(),
            'ends_at'   => now()->addDays(4)->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    }

    public function test_only_managers_update_and_delete(): void
    {
        $event = $this->createEvent();

        $this->actingAs($this->user)->putJson("/api/v1/events/{$event->id}", ['title' => 'Piraté'])->assertForbidden();
        $this->actingAs($this->org)->putJson("/api/v1/events/{$event->id}", ['title' => 'Croisade 2026'])
            ->assertOk()->assertJsonPath('data.title', 'Croisade 2026');
        $this->actingAs($this->admin)->putJson("/api/v1/events/{$event->id}", ['status' => 'cancelled'])
            ->assertOk()->assertJsonPath('data.phaseLabel', 'Annulé');

        $this->actingAs($this->user)->deleteJson("/api/v1/events/{$event->id}")->assertForbidden();
        $this->actingAs($this->org)->deleteJson("/api/v1/events/{$event->id}")->assertOk();
        $this->assertSoftDeleted($event);
    }

    public function test_draft_is_hidden_from_public(): void
    {
        $event = $this->createEvent(extra: ['status' => 'draft']);

        $this->guest()->getJson("/api/v1/events/{$event->id}")->assertNotFound();
        $this->guest()->getJson('/api/v1/events')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->org)->getJson('/api/v1/events?scope=mine')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_listing_upcoming_and_past(): void
    {
        $this->createEvent();
        $past = $this->createEvent(extra: [
            'starts_at' => now()->subDays(5)->toIso8601String(),
            'ends_at'   => now()->subDays(4)->toIso8601String(),
        ]);

        $this->guest()->getJson('/api/v1/events')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.canCreate', false);
        $this->guest()->getJson('/api/v1/events?scope=past')->assertOk()
            ->assertJsonPath('data.0.id', $past->id)->assertJsonPath('data.0.phase', 'past');
        $this->actingAs($this->org)->getJson('/api/v1/events')->assertJsonPath('meta.canCreate', true);
    }

    // ─── Images ──────────────────────────────────────────────────────────────

    public function test_cover_images_carousel(): void
    {
        Storage::fake('public');
        $event = $this->createEvent();

        $first = $this->actingAs($this->org)->post("/api/v1/events/{$event->id}/images",
            ['image' => UploadedFile::fake()->image('a.jpg', 1200, 600)], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.images.0.id');
        $second = $this->actingAs($this->org)->post("/api/v1/events/{$event->id}/images",
            ['image' => UploadedFile::fake()->image('b.jpg', 1200, 600)], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.images.1.id');

        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/images/{$second}/cover")
            ->assertOk()->assertJsonPath('data.images.0.id', $second);

        $this->actingAs($this->user)->deleteJson("/api/v1/events/{$event->id}/images/{$first}")->assertForbidden();
        $this->actingAs($this->org)->deleteJson("/api/v1/events/{$event->id}/images/{$first}")
            ->assertOk()->assertJsonCount(1, 'data.images');

        // Image trop petite refusée.
        $this->actingAs($this->org)->post("/api/v1/events/{$event->id}/images",
            ['image' => UploadedFile::fake()->image('c.jpg', 200, 100)], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    // ─── Participation ───────────────────────────────────────────────────────

    public function test_participation(): void
    {
        $event = $this->createEvent();

        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/participation", ['status' => 'going'])
            ->assertOk()->assertJsonPath('data.myParticipation', 'going')->assertJsonPath('data.stats.going', 1);
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/participation", ['status' => 'not_going'])
            ->assertOk()->assertJsonPath('data.stats.going', 0)->assertJsonPath('data.stats.notGoing', 1);
        $this->actingAs($this->user)->deleteJson("/api/v1/events/{$event->id}/participation")
            ->assertOk()->assertJsonPath('data.myParticipation', null)->assertJsonPath('data.stats.notGoing', 0);

        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/participation", ['status' => 'going']);
        $this->actingAs($this->user)->getJson("/api/v1/events/{$event->id}/participants")->assertForbidden();
        $this->actingAs($this->org)->getJson("/api/v1/events/{$event->id}/participants")
            ->assertOk()->assertJsonPath('data.0.id', $this->user->id);
        $this->actingAs($this->user)->getJson('/api/v1/events?scope=going')->assertJsonCount(1, 'data');
    }

    public function test_no_participation_once_cancelled(): void
    {
        $event = $this->createEvent(extra: ['status' => 'cancelled']);

        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/participation", ['status' => 'going'])
            ->assertUnprocessable()->assertJsonPath('message', 'Cet événement est annulé.');
    }

    // ─── Commentaires et témoignages officiels ───────────────────────────────

    public function test_comments_and_promotion_by_organizer_go_to_moderation(): void
    {
        $event = $this->createEvent();
        $commentId = $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/comments", [
            'body' => "J'ai été guéri pendant la croisade, gloire à Dieu !",
        ])->assertCreated()->json('data.id');

        $this->guest()->getJson("/api/v1/events/{$event->id}/comments")->assertOk()->assertJsonPath('data.0.id', $commentId);

        // Un simple participant ne peut pas promouvoir.
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/comments/{$commentId}/promote")->assertForbidden();

        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/comments/{$commentId}/promote", ['title' => 'Guéri à la croisade'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Guéri à la croisade')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.eventId', $event->id)
            ->assertJsonPath('data.userId', $this->user->id); // l'auteur reste le participant

        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/comments/{$commentId}/promote")->assertStatus(409);

        // En attente : visible du gestionnaire, pas du public.
        $this->guest()->getJson("/api/v1/events/{$event->id}/testimonies")->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->org)->getJson("/api/v1/events/{$event->id}/testimonies")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_promotion_by_admin_is_published(): void
    {
        $event = $this->createEvent();
        $commentId = $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/comments", ['body' => 'Merci Seigneur pour ce camp.'])
            ->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/v1/events/{$event->id}/comments/{$commentId}/promote")
            ->assertCreated()->assertJsonPath('data.status', 'approved');
        $this->guest()->getJson("/api/v1/events/{$event->id}/testimonies")->assertJsonCount(1, 'data');
    }

    public function test_comment_deletion_rights(): void
    {
        $event = $this->createEvent();
        $other = $this->makeUser('autre');
        $commentId = $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/comments", ['body' => 'Béni !'])->json('data.id');

        $this->actingAs($other)->deleteJson("/api/v1/events/{$event->id}/comments/{$commentId}")->assertForbidden();
        $this->actingAs($this->org)->deleteJson("/api/v1/events/{$event->id}/comments/{$commentId}")->assertOk();
        $this->assertSame(0, $event->fresh()->comment_count);
    }

    public function test_comments_closed(): void
    {
        $event = $this->createEvent(extra: ['comments_enabled' => false]);

        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/comments", ['body' => 'Bonjour'])->assertForbidden();
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/comments", ['body' => 'Programme mis à jour'])->assertCreated();
    }

    public function test_publish_testimony_attached_to_event(): void
    {
        Category::create(['slug' => 'guerison', 'name' => 'Guérison']);
        $event = $this->createEvent();
        $body = ['title' => 'Témoignage du camp', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Un récit.', 'event_id' => $event->id];

        $this->actingAs($this->user)->postJson('/api/v1/testimonies', $body)->assertForbidden();
        $this->actingAs($this->org)->postJson('/api/v1/testimonies', $body)->assertCreated()->assertJsonPath('data.eventId', $event->id);
        $this->assertSame(1, Testimony::where('event_id', $event->id)->count());
    }

    // ─── Direct de l'événement ───────────────────────────────────────────────

    public function test_organizer_can_start_a_live_only_for_its_event(): void
    {
        config([
            'livekit.url'        => 'wss://lk.test',
            'livekit.api_key'    => 'devkey',
            'livekit.api_secret' => 'secret-de-test-suffisamment-long',
        ]);
        $this->app->forgetInstance(LiveKitClient::class);
        Http::fake(['lk.test/*' => Http::response(['participants' => []], 200)]);

        $event = $this->createEvent();

        // Sans événement : toujours réservé à la modération.
        $this->actingAs($this->org)->postJson('/api/v1/lives', ['title' => 'Direct libre'])->assertForbidden();
        // Événement d'un autre organisateur : refusé.
        $otherEvent = $this->createEvent($this->admin);
        $this->actingAs($this->org)->postJson('/api/v1/lives', ['title' => 'Direct', 'event_id' => $otherEvent->id])->assertForbidden();

        $this->actingAs($this->org)->postJson('/api/v1/lives', ['title' => 'Croisade en direct', 'event_id' => $event->id])
            ->assertCreated();
        $live = LiveSession::where('host_id', $this->org->id)->firstOrFail();
        $this->assertSame($event->id, $live->event_id);

        $this->actingAs($this->org)->getJson("/api/v1/events/{$event->id}")
            ->assertJsonPath('data.live.id', $live->id)->assertJsonPath('data.live.status', 'preparing');
        // Un direct en préparation n'est pas annoncé au public.
        $this->guest()->getJson("/api/v1/events/{$event->id}")->assertJsonPath('data.live', null);
    }

    // ─── Gestionnaires ───────────────────────────────────────────────────────

    public function test_event_co_managers_at_most_two(): void
    {
        $event = $this->createEvent();
        [$a, $b, $c] = [$this->makeUser('a'), $this->makeUser('b'), $this->makeUser('c')];

        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/managers", ['user_id' => $a->id])->assertForbidden();
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/managers", ['user_id' => $a->id])
            ->assertOk()->assertJsonPath('data.managers.0.id', $a->id);
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/managers", ['user_id' => $a->id])->assertStatus(409);
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/managers", ['user_id' => $b->id])->assertOk();
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/managers", ['user_id' => $c->id])
            ->assertUnprocessable();

        // Un co-gestionnaire gère la page, mais ne la supprime pas et ne désigne personne.
        $this->actingAs($a)->getJson("/api/v1/events/{$event->id}")
            ->assertJsonPath('data.canManage', true)->assertJsonPath('data.canAdministrate', false);
        $this->actingAs($a)->putJson("/api/v1/events/{$event->id}", ['title' => 'Nouveau titre'])->assertOk();
        $this->actingAs($a)->postJson("/api/v1/events/{$event->id}/managers", ['user_id' => $c->id])->assertForbidden();
        $this->actingAs($a)->deleteJson("/api/v1/events/{$event->id}")->assertForbidden();
        $this->actingAs($a)->getJson('/api/v1/events?scope=mine')->assertJsonCount(1, 'data');

        // Il peut se retirer lui-même.
        $this->actingAs($a)->deleteJson("/api/v1/events/{$event->id}/managers/{$a->id}")->assertOk();
        $this->actingAs($a)->getJson("/api/v1/events/{$event->id}")->assertJsonPath('data.canManage', false);
    }

    public function test_organization_managers_at_most_two_and_act_for_it(): void
    {
        [$a, $b, $c] = [$this->makeUser('a'), $this->makeUser('b'), $this->makeUser('c')];

        $this->actingAs($this->user)->getJson('/api/v1/users/me/managers')->assertUnprocessable();
        $this->actingAs($this->org)->postJson('/api/v1/users/me/managers', ['user_id' => $a->id])
            ->assertOk()->assertJsonPath('data.managers.0.id', $a->id);
        $this->actingAs($this->org)->postJson('/api/v1/users/me/managers', ['user_id' => $b->id])->assertOk();
        $this->actingAs($this->org)->postJson('/api/v1/users/me/managers', ['user_id' => $c->id])->assertUnprocessable();
        $this->actingAs($this->org)->postJson('/api/v1/users/me/managers', ['user_id' => $this->pendingOrg->id])
            ->assertUnprocessable();

        // Le gestionnaire crée au nom de l'organisation et gère ses événements.
        $this->actingAs($a)->getJson('/api/v1/events')
            ->assertJsonPath('meta.canCreate', true)->assertJsonPath('meta.organizations.0.id', $this->org->id);
        $id = $this->actingAs($a)->postJson('/api/v1/events', $this->payload())
            ->assertCreated()->assertJsonPath('data.organizer.id', $this->org->id)->json('data.id');
        $orgEvent = $this->createEvent();
        $this->actingAs($a)->getJson("/api/v1/events/{$orgEvent->id}")->assertJsonPath('data.canAdministrate', true);
        $this->actingAs($a)->getJson('/api/v1/users/me/managed-organizations')->assertJsonPath('data.0.id', $this->org->id);

        // Retiré : plus aucun droit.
        $this->actingAs($this->org)->deleteJson("/api/v1/users/me/managers/{$a->id}")->assertOk();
        $this->actingAs($a)->putJson("/api/v1/events/{$id}", ['title' => 'X'])->assertForbidden();
        $this->actingAs($a)->postJson('/api/v1/events', $this->payload())->assertForbidden();
    }

    public function test_manager_of_unverified_organization_cannot_create(): void
    {
        $a = $this->makeUser('a');
        $this->actingAs($this->pendingOrg)->postJson('/api/v1/users/me/managers', ['user_id' => $a->id])->assertOk();

        $this->actingAs($a)->postJson('/api/v1/events', $this->payload())->assertForbidden();
    }

    public function test_co_manager_can_start_the_event_live(): void
    {
        config([
            'livekit.url'        => 'wss://lk.test',
            'livekit.api_key'    => 'devkey',
            'livekit.api_secret' => 'secret-de-test-suffisamment-long',
        ]);
        $this->app->forgetInstance(LiveKitClient::class);
        Http::fake(['lk.test/*' => Http::response(['participants' => []], 200)]);

        $event = $this->createEvent();
        $a = $this->makeUser('a');
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/managers", ['user_id' => $a->id])->assertOk();

        $this->actingAs($a)->postJson('/api/v1/lives', ['title' => 'Soirée 1', 'event_id' => $event->id])->assertCreated();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Types d'événements gérés par les administrateurs (table event_types) : liste publique, gestion par l'API
 * et le site, validation des événements, libellés et icônes. Voir docs/fonctionnalites/types-evenements.md
 */
class EventTypesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $moderator;
    private User $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin     = $this->makeUser('admin', ['role' => 'administrateur']);
        $this->moderator = $this->makeUser('modo', ['role' => 'moderateur']);
        $this->org       = $this->makeUser('org', [
            'account_type' => 'organization', 'organization_name' => 'Église de la Grâce',
            'organization_type' => 'church', 'verification_status' => 'verified',
        ]);
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
        ], $extra);
    }

    private function makeEvent(string $type): Event
    {
        return Event::create([
            'organizer_id' => $this->org->id, 'title' => 'Événement ' . $type, 'type' => $type, 'status' => 'published',
            'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(4),
        ]);
    }

    private function type(string $slug): EventType
    {
        return EventType::where('slug', $slug)->firstOrFail();
    }

    // ─── Données initiales ───────────────────────────────────────────────────

    public function test_migration_seeds_the_eight_former_types_in_order(): void
    {
        $this->assertSame(
            ['crusade', 'conference', 'seminar', 'camp', 'tour', 'worship', 'retreat', 'other'],
            EventType::query()->ordered()->pluck('slug')->all()
        );
        $this->assertSame('bullhorn', $this->type('crusade')->icon);
        $this->assertSame('chalkboard', $this->type('seminar')->icon);
        $this->assertSame('Concert de louange', $this->type('worship')->name_fr);
        $this->assertSame('Worship concert', $this->type('worship')->name_en);
    }

    // ─── Liste publique ──────────────────────────────────────────────────────

    public function test_public_list_returns_active_types_in_order_with_counts(): void
    {
        $this->type('camp')->update(['is_active' => false]);
        $this->type('other')->update(['position' => 0]);
        $this->makeEvent('crusade');
        $this->makeEvent('crusade');

        $res = $this->getJson('/api/v1/event-types')->assertOk()->assertJsonPath('success', true);
        $data = collect($res->json('data'));

        $this->assertSame('other', $data->first()['slug']);
        $this->assertNotContains('camp', $data->pluck('slug'));
        $this->assertCount(7, $data);
        $crusade = $data->firstWhere('slug', 'crusade');
        $this->assertSame(2, $crusade['eventsCount']);
        $this->assertSame('Croisade d\'évangélisation', $crusade['name']);
        $this->assertSame('bullhorn', $crusade['icon']);
        $this->assertTrue($crusade['isActive']);
        $this->assertSame('Evangelistic crusade', $crusade['nameEn']);
        $this->assertArrayHasKey('position', $crusade);
        $this->assertIsInt($crusade['id']);
    }

    public function test_public_list_localizes_names_in_english_with_french_fallback(): void
    {
        $this->type('camp')->update(['name_en' => null]);

        $data = collect($this->withHeader('Accept-Language', 'en')->getJson('/api/v1/event-types')->json('data'));

        $this->assertSame('Evangelistic crusade', $data->firstWhere('slug', 'crusade')['name']);
        $this->assertSame('Camp', $data->firstWhere('slug', 'camp')['name']);
    }

    public function test_all_parameter_includes_inactive_types_for_administrators_only(): void
    {
        $this->type('camp')->update(['is_active' => false]);

        $this->assertCount(7, $this->getJson('/api/v1/event-types?all=1')->json('data'));
        $this->assertCount(7, $this->actingAs($this->moderator)->getJson('/api/v1/event-types?all=1')->json('data'));
        $all = collect($this->actingAs($this->admin)->getJson('/api/v1/event-types?all=1')->json('data'));
        $this->assertCount(8, $all);
        $this->assertFalse($all->firstWhere('slug', 'camp')['isActive']);
    }

    // ─── Gestion par l'API ───────────────────────────────────────────────────

    public function test_admin_creates_a_type_with_automatic_unique_slug(): void
    {
        $res = $this->actingAs($this->admin)->postJson('/api/v1/admin/event-types', [
            'name_fr' => 'Camp de jeunes', 'name_en' => 'Youth camp', 'icon' => 'child',
        ])->assertCreated();

        $res->assertJsonPath('data.slug', 'camp-de-jeunes')
            ->assertJsonPath('data.name', 'Camp de jeunes')
            ->assertJsonPath('data.nameEn', 'Youth camp')
            ->assertJsonPath('data.icon', 'child')
            ->assertJsonPath('data.position', 9)
            ->assertJsonPath('data.isActive', true)
            ->assertJsonPath('data.eventsCount', 0);

        // Même nom : identifiant rendu unique.
        $this->actingAs($this->admin)->postJson('/api/v1/admin/event-types', ['name_fr' => 'Camp de jeunes'])
            ->assertCreated()->assertJsonPath('data.slug', 'camp-de-jeunes-2')->assertJsonPath('data.icon', 'calendar');

        // Nouveau type immédiatement proposé.
        $this->assertContains('camp-de-jeunes', collect($this->getJson('/api/v1/event-types')->json('data'))->pluck('slug'));
    }

    public function test_create_validates_slug_icon_and_name(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/event-types', ['name_fr' => 'X', 'slug' => 'crusade'])
            ->assertStatus(422)->assertJsonValidationErrors('slug');
        $this->actingAs($this->admin)->postJson('/api/v1/admin/event-types', ['name_fr' => 'X', 'slug' => 'Pas Bon'])
            ->assertStatus(422)->assertJsonValidationErrors('slug');
        $this->actingAs($this->admin)->postJson('/api/v1/admin/event-types', ['name_fr' => 'X', 'icon' => 'rocket'])
            ->assertStatus(422)->assertJsonValidationErrors('icon');
        $this->actingAs($this->admin)->postJson('/api/v1/admin/event-types', ['name_fr' => str_repeat('a', 81)])
            ->assertStatus(422)->assertJsonValidationErrors('name_fr');
        $this->actingAs($this->admin)->postJson('/api/v1/admin/event-types', ['name_en' => 'Only english'])
            ->assertStatus(422)->assertJsonValidationErrors('name_fr');

        $this->actingAs($this->admin)->postJson('/api/v1/admin/event-types', ['name_fr' => 'Veillée', 'slug' => 'veillee-2026', 'is_active' => false])
            ->assertCreated()->assertJsonPath('data.slug', 'veillee-2026')->assertJsonPath('data.isActive', false);
    }

    public function test_moderators_and_users_cannot_manage_types(): void
    {
        $id = $this->type('camp')->id;
        foreach ([$this->moderator, $this->org] as $user) {
            $this->actingAs($user)->postJson('/api/v1/admin/event-types', ['name_fr' => 'X'])->assertForbidden();
            $this->actingAs($user)->putJson("/api/v1/admin/event-types/{$id}", ['name_fr' => 'X'])->assertForbidden();
            $this->actingAs($user)->deleteJson("/api/v1/admin/event-types/{$id}")->assertForbidden();
            $this->actingAs($user)->putJson('/api/v1/admin/event-types/reorder', ['ids' => [$id]])->assertForbidden();
        }
        $this->assertDatabaseHas('event_types', ['id' => $id, 'name_fr' => 'Camp']);
    }

    public function test_admin_updates_a_type_but_never_its_slug(): void
    {
        $camp = $this->type('camp');

        $this->actingAs($this->admin)->putJson("/api/v1/admin/event-types/{$camp->id}", [
            'name_fr' => 'Camp biblique', 'name_en' => 'Bible camp', 'icon' => 'book', 'is_active' => false, 'position' => 20, 'slug' => 'nouveau',
        ])->assertOk()
            ->assertJsonPath('data.slug', 'camp')
            ->assertJsonPath('data.name', 'Camp biblique')
            ->assertJsonPath('data.icon', 'book')
            ->assertJsonPath('data.isActive', false)
            ->assertJsonPath('data.position', 20);

        $this->assertDatabaseHas('event_types', ['id' => $camp->id, 'slug' => 'camp', 'name_en' => 'Bible camp']);
        $this->actingAs($this->admin)->putJson("/api/v1/admin/event-types/{$camp->id}", ['icon' => 'rocket'])->assertStatus(422);
    }

    public function test_delete_unused_type(): void
    {
        $camp = $this->type('camp');
        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/event-types/{$camp->id}")->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('event_types', ['slug' => 'camp']);
        $this->assertNotContains('camp', collect($this->getJson('/api/v1/event-types')->json('data'))->pluck('slug'));
    }

    public function test_delete_used_type_requires_replacement_then_moves_events(): void
    {
        $camp = $this->type('camp');
        $a = $this->makeEvent('camp');
        $b = $this->makeEvent('camp');
        $b->delete(); // supprimé : déplacé aussi

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/event-types/{$camp->id}")
            ->assertStatus(409)->assertJsonPath('success', false)->assertJsonPath('eventsCount', 1)
            ->assertJsonStructure(['message', 'errors' => ['events']]);
        // Remplaçant inconnu, inactif ou lui-même : refusé.
        $this->type('retreat')->update(['is_active' => false]);
        foreach (['nope', 'retreat', 'camp'] as $slug) {
            $this->actingAs($this->admin)->deleteJson("/api/v1/admin/event-types/{$camp->id}", ['replace_with' => $slug])->assertStatus(409);
        }
        $this->assertDatabaseHas('event_types', ['slug' => 'camp']);

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/event-types/{$camp->id}", ['replace_with' => 'seminar'])->assertOk();

        $this->assertDatabaseMissing('event_types', ['slug' => 'camp']);
        $this->assertSame('seminar', $a->fresh()->type);
        $this->assertSame('seminar', Event::withTrashed()->find($b->id)->type);
    }

    public function test_last_active_type_can_neither_be_deleted_nor_deactivated(): void
    {
        EventType::where('slug', '!=', 'other')->update(['is_active' => false]);
        EventType::clearCache();
        $other = $this->type('other');

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/event-types/{$other->id}")->assertStatus(422);
        $this->actingAs($this->admin)->putJson("/api/v1/admin/event-types/{$other->id}", ['is_active' => false])->assertStatus(422);
        $this->assertDatabaseHas('event_types', ['slug' => 'other', 'is_active' => true]);

        // Un type inactif peut toujours être supprimé.
        $camp = $this->type('camp');
        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/event-types/{$camp->id}")->assertOk();
    }

    public function test_reorder_returns_the_full_list(): void
    {
        $ids = EventType::query()->ordered()->pluck('id')->reverse()->values()->all();
        $this->type('camp')->update(['is_active' => false]);

        $res = $this->actingAs($this->admin)->putJson('/api/v1/admin/event-types/reorder', ['ids' => $ids])->assertOk();

        $this->assertSame(['other', 'retreat', 'worship', 'tour', 'camp', 'seminar', 'conference', 'crusade'], collect($res->json('data'))->pluck('slug')->all());
        $this->assertSame(range(1, 8), collect($res->json('data'))->pluck('position')->all());
        $this->assertSame('other', $this->getJson('/api/v1/event-types')->json('data.0.slug'));

        $this->actingAs($this->admin)->putJson('/api/v1/admin/event-types/reorder', [])->assertStatus(422);
    }

    // ─── Événements ──────────────────────────────────────────────────────────

    public function test_event_validation_rejects_unknown_and_inactive_types(): void
    {
        $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload(['type' => 'inconnu']))
            ->assertStatus(422)->assertJsonValidationErrors('type');

        $this->type('camp')->update(['is_active' => false]);
        $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload(['type' => 'camp']))
            ->assertStatus(422)->assertJsonValidationErrors('type');

        $this->actingAs($this->admin)->postJson('/api/v1/admin/event-types', ['name_fr' => 'Veillée de prière', 'icon' => 'hands']);
        $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload(['type' => 'veillee-de-priere']))
            ->assertCreated()
            ->assertJsonPath('data.type', 'veillee-de-priere')
            ->assertJsonPath('data.typeLabel', 'Veillée de prière')
            ->assertJsonPath('data.typeIcon', 'hands');
    }

    public function test_event_keeps_its_inactive_type_when_edited(): void
    {
        $id = $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload(['type' => 'camp']))->assertCreated()->json('data.id');
        $this->type('camp')->update(['is_active' => false]);

        $this->actingAs($this->org)->putJson("/api/v1/events/{$id}", ['title' => 'Nouveau titre', 'type' => 'camp'])
            ->assertOk()->assertJsonPath('data.type', 'camp')->assertJsonPath('data.typeLabel', 'Camp');
        // Mais ne peut pas passer à un autre type inactif.
        $this->type('tour')->update(['is_active' => false]);
        $this->actingAs($this->org)->putJson("/api/v1/events/{$id}", ['type' => 'tour'])->assertStatus(422);

        // Site : même règle.
        $event = Event::findOrFail($id);
        $this->actingAs($this->org)->put(route('events.update', $id), [
            'title' => 'Titre site', 'type' => 'camp', 'status' => 'published',
            'starts_at' => $event->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $event->ends_at->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
        $this->assertSame('Titre site', $event->fresh()->title);
    }

    public function test_event_resource_label_icon_and_meta_types(): void
    {
        $event = $this->makeEvent('worship');

        $this->getJson("/api/v1/events/{$event->id}")->assertOk()
            ->assertJsonPath('data.type', 'worship')
            ->assertJsonPath('data.typeLabel', 'Concert de louange')
            ->assertJsonPath('data.typeIcon', 'music');
        $this->withHeader('Accept-Language', 'en')->getJson("/api/v1/events/{$event->id}")
            ->assertJsonPath('data.typeLabel', 'Worship concert');
        $this->withHeader('Accept-Language', '');

        // Slug inconnu (type supprimé hors application) : slug « humanisé », icône par défaut.
        Event::whereKey($event->id)->update(['type' => 'camp-jeunes']);
        $this->getJson("/api/v1/events/{$event->id}")
            ->assertJsonPath('data.typeLabel', 'Camp jeunes')->assertJsonPath('data.typeIcon', 'calendar');

        $this->type('tour')->update(['is_active' => false, 'name_fr' => 'Tournée !']);
        $types = collect($this->getJson('/api/v1/events')->assertOk()->json('meta.types'));
        $this->assertNotContains('tour', $types->pluck('value'));
        $this->assertSame(['value' => 'crusade', 'label' => 'Croisade d\'évangélisation', 'icon' => 'bullhorn'], $types->first());
    }

    public function test_type_filter_still_works(): void
    {
        $this->makeEvent('camp');
        $this->makeEvent('tour');

        $data = $this->getJson('/api/v1/events?type=camp')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('camp', $data[0]['type']);
        $this->assertCount(2, $this->getJson('/api/v1/events?type=inconnu')->json('data'));

        $this->get(route('events.index', ['type' => 'camp']))->assertOk()->assertSee('Événement camp')->assertDontSee('Événement tour');
    }

    // ─── Site ────────────────────────────────────────────────────────────────

    public function test_web_admin_page_renders_for_admins_only(): void
    {
        $this->makeEvent('camp');

        $this->actingAs($this->admin)->get(route('admin.event-types.index'))->assertOk()
            ->assertSee('Types d’événements')
            ->assertSee('Concert de louange')
            ->assertSee('fa-chalkboard-user')
            ->assertSee('fa-graduation-cap')
            ->assertSee('Remplacer par');

        $this->actingAs($this->moderator)->get(route('admin.event-types.index'))->assertForbidden();
    }

    public function test_web_admin_manages_types(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.event-types.store'), ['_form' => 'create', 'name_fr' => 'Veillée', 'name_en' => 'Vigil', 'icon' => 'fire', 'is_active' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $vigil = $this->type('veillee');
        $this->assertSame('fire', $vigil->icon);
        $this->assertSame(9, $vigil->position);

        $this->put(route('admin.event-types.update', $vigil->id), ['_form' => 'edit', 'name_fr' => 'Veillée de nuit', 'icon' => 'star'])
            ->assertSessionHasNoErrors();
        $vigil->refresh();
        $this->assertSame('Veillée de nuit', $vigil->name_fr);
        $this->assertFalse($vigil->is_active); // case décochée

        $this->post(route('admin.event-types.toggle', $vigil->id))->assertSessionHasNoErrors();
        $this->assertTrue($vigil->fresh()->is_active);

        $this->post(route('admin.event-types.move', $vigil->id), ['direction' => 'up']);
        $this->assertSame(8, $vigil->fresh()->position);
        $this->assertSame(9, $this->type('other')->position);

        $ids = EventType::query()->ordered()->pluck('id')->reverse()->values()->all();
        $this->putJson(route('admin.event-types.reorder'), ['ids' => $ids])->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, $this->type('other')->position);

        // Suppression : remplaçant demandé si des événements utilisent le type.
        $event = $this->makeEvent('camp');
        $camp  = $this->type('camp');
        $this->delete(route('admin.event-types.destroy', $camp->id))->assertSessionHasErrors('replace_with');
        $this->assertDatabaseHas('event_types', ['slug' => 'camp']);
        $this->delete(route('admin.event-types.destroy', $camp->id), ['replace_with' => 'veillee'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('event_types', ['slug' => 'camp']);
        $this->assertSame('veillee', $event->fresh()->type);

        // Formulaire et pages d'événements : libellés et icônes de la table.
        $this->get(route('events.create'))->assertOk()->assertSee('Veillée de nuit')->assertDontSee('value="camp"', false);
        $this->get(route('events.show', $event->id))->assertOk()->assertSee('Veillée de nuit')->assertSee('fa-star');
        $this->get(route('events.index'))->assertOk()->assertSee('fa-star');
    }
}

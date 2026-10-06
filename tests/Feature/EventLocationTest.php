<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lieu des événements : adresse, repère GPS (carte OpenStreetMap) et lien d'itinéraire.
 * Voir docs/fonctionnalites/evenements.md
 */
class EventLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = User::create([
            'display_name' => 'Org', 'email' => 'org@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
            'account_type' => 'organization', 'organization_name' => 'Église de la Grâce',
            'organization_type' => 'church', 'verification_status' => 'verified',
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'title'     => 'Grande croisade de Cotonou',
            'type'      => 'crusade',
            'starts_at' => now()->addDays(10)->toIso8601String(),
            'ends_at'   => now()->addDays(12)->toIso8601String(),
            'location'  => "Stade de l'Amitié",
            'city'      => 'Cotonou',
        ], $extra);
    }

    public function test_create_with_address_and_coordinates(): void
    {
        $res = $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload([
            'address' => 'Boulevard de la Marina', 'latitude' => 6.37029281, 'longitude' => '2.391236',
        ]))->assertCreated();

        $res->assertJsonPath('data.address', 'Boulevard de la Marina')
            ->assertJsonPath('data.latitude', 6.3702928)
            ->assertJsonPath('data.longitude', 2.391236)
            ->assertJsonPath('data.directionsUrl', 'https://www.google.com/maps/dir/?api=1&destination=6.3702928,2.391236');
    }

    public function test_directions_fall_back_to_address_and_coordinates_go_together(): void
    {
        $id = $this->actingAs($this->org)->postJson('/api/v1/events', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.latitude', null)
            ->assertJsonPath('data.directionsUrl', 'https://www.google.com/maps/dir/?api=1&destination=Stade%20de%20l%27Amiti%C3%A9%2C%20Cotonou')
            ->json('data.id');

        $this->putJson("/api/v1/events/{$id}", ['latitude' => 6.37])->assertStatus(422)->assertJsonValidationErrors('longitude');
        $this->putJson("/api/v1/events/{$id}", ['latitude' => 95, 'longitude' => 2])->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->putJson("/api/v1/events/{$id}", ['latitude' => 6.5, 'longitude' => 200])->assertStatus(422)->assertJsonValidationErrors('longitude');

        $this->putJson("/api/v1/events/{$id}", ['latitude' => 6.5, 'longitude' => 2.6])->assertOk()->assertJsonPath('data.latitude', 6.5);
        // Effacer le repère.
        $this->putJson("/api/v1/events/{$id}", ['latitude' => null, 'longitude' => null])->assertOk()->assertJsonPath('data.longitude', null);
        $this->assertNull(Event::find($id)->latitude);

        $event = new Event();
        $this->assertNull($event->directionsUrl());
    }

    public function test_web_form_and_page_show_the_map(): void
    {
        $this->actingAs($this->org)->get('/evenements/creer')->assertOk()
            ->assertSee('data-event-map-picker', false)->assertSee('Ma position');

        $this->post('/evenements', $this->payload([
            'status' => 'published', 'address' => 'Boulevard de la Marina', 'latitude' => '6.3702928', 'longitude' => '2.391236',
        ]))->assertRedirect();
        $event = Event::firstOrFail();
        $this->assertSame(6.3702928, $event->latitude);

        $this->get("/evenements/{$event->id}")->assertOk()
            ->assertSee('data-event-map-view', false)
            ->assertSee('Itinéraire')
            ->assertSee('destination=6.3702928,2.391236', false)
            ->assertSee('tile.openstreetmap.org', false);
    }
}

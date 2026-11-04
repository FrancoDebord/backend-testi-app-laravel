<?php

namespace Tests\Feature;

use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bouton « Je prie avec Prénom » : prénom de l'auteur (nom de l'organisation), raccourci s'il est long,
 * nom complet dans l'infobulle ; « Je prie » seul si anonyme ou pour l'auteur. Voir docs/fonctionnalites/reactions.md
 */
class PrayWithNameTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => $name, 'email' => str($name)->slug() . '@example.com',
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    public function test_short_first_name_takes_length_into_account(): void
    {
        $this->assertSame('Marie', $this->user('Marie Dupont')->shortFirstName());
        $this->assertSame('Jean-Baptiste', $this->user('Jean-Baptiste Akpovi')->shortFirstName());
        $this->assertSame('Maximilienne…', $this->user('Maximilienne-Marguerite Kossou')->shortFirstName());
        $church = $this->user('Pasteur Paul', ['account_type' => 'organization', 'organization_name' => 'Église de la Moisson Abondante', 'organization_type' => 'church']);
        $this->assertSame('Église de la…', $church->shortFirstName());
        $this->assertSame('Église', $this->user('Église', ['account_type' => 'organization', 'organization_name' => 'Église', 'organization_type' => 'church'])->shortFirstName());
    }

    public function test_pray_buttons_name_the_author(): void
    {
        $author = $this->user('Marie Dupont');
        $reader = $this->user('Paul Lecteur');
        $request = PrayerRequest::create(['user_id' => $author->id, 'body' => 'Priez pour ma famille', 'visibility' => 'public', 'status' => 'open']);
        $anonymous = PrayerRequest::create(['user_id' => $author->id, 'body' => 'Sujet discret', 'visibility' => 'public', 'status' => 'open', 'is_anonymous' => true]);
        $testimony = Testimony::create(['user_id' => $author->id, 'title' => 'Guérison', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now()]);

        $this->actingAs($reader)->get("/priere/requetes/{$request->id}")->assertOk()
            ->assertSee('Je prie avec Marie')->assertSee('title="Je prie avec Marie Dupont"', false);
        $this->actingAs($reader)->get("/priere/requetes/{$anonymous->id}")->assertOk()
            ->assertDontSee('Je prie avec Marie')->assertSee('Je prie');
        $this->actingAs($reader)->get("/testimonies/{$testimony->id}")->assertOk()->assertSee('Je prie avec Marie');

        // L'auteur ne prie pas « avec lui-même ».
        $this->actingAs($author)->get("/priere/requetes/{$request->id}")->assertOk()->assertDontSee('Je prie avec Marie');
        $this->actingAs($author)->get("/testimonies/{$testimony->id}")->assertOk()->assertDontSee('Je prie avec Marie');
    }
}

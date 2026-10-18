<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Page des notifications du site : le nom de l'auteur n'apparaît qu'une fois, que le message commence
 * déjà par ce nom (« Marie a aimé votre commentaire ») ou non.
 */
class WebNotificationActorNameTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name): User
    {
        return User::create([
            'display_name' => $name, 'email' => strtolower($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active', 'is_active' => true,
        ]);
    }

    private function notify(User $recipient, ?User $actor, string $type, string $message): void
    {
        AppNotification::create([
            'recipient_id' => $recipient->id, 'actor_id' => $actor?->id, 'actor_name' => $actor?->display_name,
            'type' => $type, 'message' => $message, 'is_read' => false, 'created_at' => now(),
        ]);
    }

    public function test_actor_name_is_shown_once_for_every_notification_type(): void
    {
        Queue::fake();
        $me    = $this->user('Paul');
        $marie = $this->user('Marie');

        $this->notify($me, $marie, 'comment_liked', 'Marie a aimé votre commentaire');
        $this->notify($me, $marie, 'comment', 'Marie a commenté votre témoignage');
        $this->notify($me, $marie, 'follow', 'vous suit désormais');
        $this->notify($me, null, 'testimony_approved', 'Votre témoignage a été approuvé');

        $html = $this->actingAs($me)->get('/notifications')->assertOk()->getContent();
        $text = preg_replace('/\s+/u', ' ', strip_tags($html));

        $this->assertStringNotContainsString('Marie Marie', $text);
        $this->assertStringContainsString('Marie a aimé votre commentaire', $text);
        $this->assertStringContainsString('Marie a commenté votre témoignage', $text);
        // Message sans le nom : le nom est ajouté devant, une fois.
        $this->assertStringContainsString('Marie vous suit désormais', $text);
        $this->assertStringContainsString('Votre témoignage a été approuvé', $text);
        // Le nom reste mis en valeur.
        $this->assertStringContainsString('<span class="font-semibold">Marie</span> a aimé votre commentaire', $html);
    }
}

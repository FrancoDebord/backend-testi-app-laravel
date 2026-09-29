<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\Testimony;
use App\Models\User;
use App\Services\FcmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Notifications de la modération sur le site web, alignées sur l'API
 * (docs/fonctionnalites/notifications-push.md).
 */
class WebModerationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;
    private User $moderator;
    private User $author;
    private User $follower;

    protected function setUp(): void
    {
        parent::setUp();

        // FCM « configuré » : seule la mise en file est vérifiée (Queue::fake), aucune requête HTTP.
        $this->dir = storage_path('framework/testing/fcm-web-moderation');
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir . '/service-account.json', json_encode([
            'project_id' => 'testi-test', 'client_email' => 'push@testi-test.iam.gserviceaccount.com', 'private_key' => 'inutilisée',
        ]));
        config(['services.fcm' => [
            'credentials' => $this->dir . '/service-account.json', 'project_id' => null, 'enabled' => null,
            'android_channel' => 'testi_notifications', 'timeout' => 5,
        ]]);
        $this->app->forgetInstance(FcmClient::class);
        Queue::fake();

        $this->moderator = $this->user('Modo', 'modo@example.com', 'moderateur');
        $this->author    = $this->user('Marie', 'marie@example.com');
        $this->follower  = $this->user('Abonné', 'abonne@example.com');
        DB::table('follows')->insert(['follower_id' => $this->follower->id, 'following_id' => $this->author->id, 'created_at' => now()]);
        DeviceToken::create(['user_id' => $this->author->id, 'token' => 'auteur', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $this->follower->id, 'token' => 'abonne', 'platform' => 'android']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function user(string $name, string $email, string $role = 'utilisateur'): User
    {
        return User::create([
            'display_name' => $name, 'email' => $email, 'password' => 'secret123', 'role' => $role, 'status' => 'active',
        ]);
    }

    private function testimony(string $visibility = 'public'): Testimony
    {
        return Testimony::create([
            'user_id' => $this->author->id, 'title' => 'Délivrance', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => $visibility, 'status' => $visibility === 'private' ? 'draft' : 'pending',
        ]);
    }

    private function assertApprovalNotifications(Testimony $testimony): void
    {
        $followed = AppNotification::where('type', 'new_followed_testimony')->get();
        $this->assertCount(1, $followed);
        $this->assertSame($this->follower->id, $followed[0]->recipient_id);
        $this->assertSame($this->author->id, $followed[0]->actor_id);
        $this->assertSame($testimony->id, $followed[0]->testimony_id);
        $this->assertSame('Marie a partagé un nouveau témoignage : "Délivrance"', $followed[0]->message);

        $approved = AppNotification::where('type', 'testimony_approved')->get();
        $this->assertCount(1, $approved);
        $this->assertSame($this->author->id, $approved[0]->recipient_id);
        $this->assertSame(2, AppNotification::count());

        Queue::assertPushed(SendPushNotificationJob::class, 2);
        Queue::assertPushed(SendPushNotificationJob::class, fn ($job) => $job->userId === $this->author->id
            && $job->data['type'] === 'testimony_approved');
        Queue::assertPushed(SendPushNotificationJob::class, fn ($job) => $job->userId === $this->follower->id
            && $job->title === 'Nouveau témoignage'
            && $job->data['testimony_id'] === $testimony->id
            && $job->data['notification_id'] === $followed[0]->id);

        $this->assertSame('approved', $testimony->fresh()->status->value);
        $this->assertSame(1, $this->author->fresh()->testimony_count);
    }

    public function test_web_approve_notifies_author_and_followers_once_with_push(): void
    {
        $testimony = $this->testimony();

        $this->actingAs($this->moderator)
            ->post("/moderation/{$testimony->id}/approve")
            ->assertRedirect(route('moderation.index'));

        $this->assertApprovalNotifications($testimony);
    }

    public function test_web_reject_notifies_author_with_reason_and_not_followers(): void
    {
        $testimony = $this->testimony();

        $this->actingAs($this->moderator)
            ->post("/moderation/{$testimony->id}/reject", ['reason' => 'spam'])
            ->assertRedirect(route('moderation.index'));

        $this->assertSame(1, AppNotification::count());
        $this->assertDatabaseHas('app_notifications', [
            'recipient_id' => $this->author->id,
            'type'         => 'testimony_rejected',
            'message'      => 'Votre témoignage "Délivrance" a été rejeté : Spam',
        ]);
        Queue::assertPushed(SendPushNotificationJob::class, 1);
        Queue::assertPushed(SendPushNotificationJob::class, fn ($job) => $job->userId === $this->author->id
            && $job->data['type'] === 'testimony_rejected');
        $this->assertSame('rejected', $testimony->fresh()->status->value);
    }

    public function test_web_moderation_ignores_private_journal_entries(): void
    {
        $entry = $this->testimony('private');

        $this->actingAs($this->moderator)->post("/moderation/{$entry->id}/approve")->assertNotFound();
        $this->actingAs($this->moderator)->post("/moderation/{$entry->id}/reject", ['reason' => 'spam'])->assertNotFound();

        $this->assertSame(0, AppNotification::count());
        Queue::assertNothingPushed();
        $this->assertSame('draft', $entry->fresh()->status->value);
    }

    public function test_api_reject_uses_french_reason_label(): void
    {
        $testimony = $this->testimony();

        Sanctum::actingAs($this->moderator);
        $this->postJson("/api/v1/moderation/{$testimony->id}/reject", ['reason' => 'inappropriateContent'])
            ->assertOk();

        $message = AppNotification::where('type', 'testimony_rejected')->value('message');
        $this->assertStringNotContainsString('InappropriateContent', $message);
        $this->assertStringEndsWith(\App\Enums\RejectionReason::InappropriateContent->label(), $message);
    }

    public function test_api_approve_behaves_the_same(): void
    {
        $testimony = $this->testimony();

        Sanctum::actingAs($this->moderator);
        $this->postJson("/api/v1/moderation/{$testimony->id}/approve")->assertOk();

        $this->assertApprovalNotifications($testimony);
    }
}

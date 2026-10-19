<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Jobs\SendPushNotificationJob;
use App\Models\AppNotification;
use App\Models\AppSetting;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\FcmClient;
use App\Services\PushNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Notifications push sur consentement : chaque notification de l'application part aussi en push, sauf si la
 * personne a coupé sa catégorie (user_settings.push_*) ou si le back-office a coupé tous les push
 * (AppSetting push_notifications_enabled). Voir docs/fonctionnalites/notifications-push.md
 */
class PushConsentTest extends TestCase
{
    use RefreshDatabase;

    private User $recipient;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(FcmClient::class, fn ($m) => $m->shouldReceive('isConfigured')->andReturn(true));
        Queue::fake();

        $this->recipient = $this->makeUser('destinataire');
        $this->actor = $this->makeUser('auteur');
        DeviceToken::create(['user_id' => $this->recipient->id, 'token' => 'jeton-destinataire', 'platform' => 'android']);
    }

    private function makeUser(string $name): User
    {
        return User::create([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    private function notify(string $type): AppNotification
    {
        return AppNotification::create([
            'recipient_id' => $this->recipient->id, 'actor_id' => $this->actor->id, 'actor_name' => 'Auteur',
            'type' => $type, 'message' => "Notification {$type}", 'created_at' => now(),
        ]);
    }

    private function pushedTypes(): array
    {
        return Queue::pushed(SendPushNotificationJob::class)->map(fn ($job) => $job->data['type'] ?? null)->all();
    }

    public function test_every_notification_type_has_a_consent_category_exposed_in_settings(): void
    {
        foreach (NotificationType::cases() as $type) {
            $this->assertArrayHasKey($type->value, PushNotifications::PREFERENCES, "Type sans catégorie push : {$type->value}");
            $this->assertContains(PushNotifications::PREFERENCES[$type->value], PushNotifications::CATEGORIES);
        }

        $settings = $this->actingAs($this->recipient)->getJson('/api/v1/users/me/settings')->assertOk()->json('data');
        foreach (PushNotifications::CATEGORIES as $column) {
            $this->assertArrayHasKey($column, $settings);
            $this->assertTrue((bool) $settings[$column], "{$column} doit être activé par défaut");
        }
    }

    public function test_each_type_is_pushed_when_allowed_and_not_when_its_category_is_disabled(): void
    {
        foreach (NotificationType::cases() as $type) {
            $this->notify($type->value);
        }
        $this->assertCount(count(NotificationType::cases()), Queue::pushed(SendPushNotificationJob::class));
        $this->assertEqualsCanonicalizing(array_map(fn ($t) => $t->value, NotificationType::cases()), $this->pushedTypes());

        // Toutes les catégories coupées : aucune nouvelle notification ne part en push (elles restent dans l'application).
        UserSetting::create(['user_id' => $this->recipient->id] + array_fill_keys(PushNotifications::CATEGORIES, false));
        foreach (NotificationType::cases() as $type) {
            $this->notify($type->value);
        }
        $this->assertCount(count(NotificationType::cases()), Queue::pushed(SendPushNotificationJob::class));
        $this->assertSame(count(NotificationType::cases()) * 2, AppNotification::where('recipient_id', $this->recipient->id)->count());
    }

    public function test_disabling_one_category_only_stops_its_types(): void
    {
        foreach (PushNotifications::CATEGORIES as $column) {
            Queue::fake();
            UserSetting::updateOrCreate(['user_id' => $this->recipient->id], array_fill_keys(PushNotifications::CATEGORIES, true));
            UserSetting::whereKey($this->recipient->id)->update([$column => false]);

            foreach (NotificationType::cases() as $type) {
                $this->notify($type->value);
            }
            $expected = collect(NotificationType::cases())->map->value
                ->reject(fn ($t) => PushNotifications::PREFERENCES[$t] === $column)->values()->all();
            $this->assertEqualsCanonicalizing($expected, $this->pushedTypes(), "Catégorie {$column}");
        }
    }

    public function test_settings_api_saves_the_new_categories(): void
    {
        $this->actingAs($this->recipient)->putJson('/api/v1/users/me/settings', [
            'push_follows' => false, 'push_events' => false, 'push_account' => false, 'push_moderation' => false,
        ])->assertOk()
            ->assertJsonPath('data.push_follows', false)->assertJsonPath('data.push_events', false)
            ->assertJsonPath('data.push_account', false)->assertJsonPath('data.push_moderation', false)
            ->assertJsonPath('data.push_comments', true);

        $this->notify('follow');
        $this->notify('event_cancelled');
        $this->notify('comment');
        $this->assertSame(['comment'], $this->pushedTypes());
    }

    public function test_web_settings_form_saves_a_refusal(): void
    {
        $this->actingAs($this->recipient)->put(route('profile.settings.update'), [
            'push_comments' => '1', 'push_likes' => '0', 'push_follows' => '0',
        ])->assertRedirect();

        $settings = UserSetting::find($this->recipient->id);
        $this->assertTrue($settings->push_comments);
        $this->assertFalse($settings->push_likes);
        $this->assertFalse($settings->push_follows);
    }

    public function test_global_switch_off_stops_every_push(): void
    {
        AppSetting::set(PushNotifications::GLOBAL_SETTING, '0');
        foreach (NotificationType::cases() as $type) {
            $this->notify($type->value);
        }
        PushNotifications::dispatch($this->recipient->id, 'Vidéo prête', 'Texte', ['type' => 'video_ready']);
        Queue::assertNotPushed(SendPushNotificationJob::class);

        AppSetting::set(PushNotifications::GLOBAL_SETTING, '1');
        $this->notify('comment');
        Queue::assertPushed(SendPushNotificationJob::class, 1);
    }

    public function test_push_only_messages_respect_their_category(): void
    {
        PushNotifications::dispatch($this->recipient->id, 'Vidéo prête', 'Texte', ['type' => 'video_ready']);
        Queue::assertPushed(SendPushNotificationJob::class, 1);

        UserSetting::create(['user_id' => $this->recipient->id, 'push_approval' => false]);
        PushNotifications::dispatch($this->recipient->id, 'Vidéo prête', 'Texte', ['type' => 'video_ready']);
        PushNotifications::dispatch($this->recipient->id, 'Vidéo non traitée', 'Texte', ['type' => 'video_failed']);
        Queue::assertPushed(SendPushNotificationJob::class, 1);
    }

    public function test_a_notification_is_pushed_once_and_never_for_ones_own_action(): void
    {
        $this->notify('comment');
        Queue::assertPushed(SendPushNotificationJob::class, 1);

        AppNotification::create([
            'recipient_id' => $this->recipient->id, 'actor_id' => $this->recipient->id,
            'type' => 'testimony_approved', 'message' => 'Approuvé', 'created_at' => now(),
        ]);
        Queue::assertPushed(SendPushNotificationJob::class, 1);
    }
}

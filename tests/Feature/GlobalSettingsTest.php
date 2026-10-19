<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\SettingsSchema;
use Database\Seeders\AppSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Paramètres globaux : mêmes réglages sur le site et l'application — docs/fonctionnalites/parametres-globaux.md */
class GlobalSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AppSettingSeeder::class);
    }

    private function user(string $name, string $role = 'utilisateur'): User
    {
        return User::create([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => $role, 'status' => 'active',
        ]);
    }

    public function test_schema_lists_groups_in_order_with_labels_descriptions_and_typed_values(): void
    {
        $res = $this->actingAs($this->user('Admin', 'administrateur'))
            ->getJson('/api/v1/admin/settings/schema')->assertOk()->assertJsonPath('success', true);

        $groups = $res->json('data.groups');
        $this->assertSame(['app', 'publishing', 'moderation', 'features', 'notifications', 'registration'], array_column($groups, 'key'));
        $this->assertSame('Général', $groups[0]['label']);
        $this->assertSame('Publication', $groups[1]['label']);
        $this->assertSame(
            ['testimony_type_text_enabled', 'testimony_type_audio_enabled', 'testimony_type_video_enabled', 'testimony_type_youtube_enabled'],
            array_column($groups[1]['settings'], 'key')
        );

        $byKey = collect($groups)->flatMap(fn ($g) => $g['settings'])->keyBy('key');
        $this->assertSame(['key' => 'maintenance_mode', 'label' => 'Mode maintenance', 'description' => 'Signale une maintenance en cours.', 'type' => 'boolean', 'value' => false], $byKey['maintenance_mode']);
        $this->assertSame(100, $byKey['max_file_size_mb']['value']);
        $this->assertSame('integer', $byKey['max_file_size_mb']['type']);
        $this->assertSame('TestiApp', $byKey['app_name']['value']);
        $this->assertNull($byKey['app_name']['description']);
        $this->assertTrue($byKey['testimony_type_youtube_enabled']['value']);
        $this->assertSame(['key' => 'push_notifications_enabled', 'label' => 'Notifications push', 'type' => 'boolean', 'value' => true],
            collect($byKey['push_notifications_enabled'])->except('description')->all());
        // Réglages obsolètes (remplacés par le groupe « publishing ») : masqués, comme sur le site.
        $this->assertFalse($byKey->has('allow_audio'));
        $this->assertFalse($byKey->has('allow_video'));
    }

    public function test_schema_is_translated_with_accept_language(): void
    {
        $res = $this->actingAs($this->user('Admin', 'administrateur'))
            ->getJson('/api/v1/admin/settings/schema', ['Accept-Language' => 'en'])->assertOk();

        $this->assertSame('General', $res->json('data.groups.0.label'));
        $this->assertSame('Maintenance mode', collect($res->json('data.groups.0.settings'))->firstWhere('key', 'maintenance_mode')['label']);
    }

    public function test_web_page_shows_exactly_the_schema_settings(): void
    {
        $admin = $this->user('Admin', 'administrateur');
        $page = $this->actingAs($admin)->get('/admin/settings')->assertOk();

        $groups = app(SettingsSchema::class)->groups();
        $labels = [];
        foreach ($groups as $group) {
            $labels[] = $group['label'];
            foreach ($group['settings'] as $setting) {
                $labels[] = $setting['label'];
                $labels[] = $setting['key'];
            }
        }
        $page->assertSeeInOrder($labels);
        $page->assertDontSee('name="allow_audio"', false)->assertDontSee('name="allow_video"', false);
    }

    public function test_generic_put_saves_typed_values(): void
    {
        $admin = $this->user('Admin', 'administrateur');
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', [
            'maintenance_mode' => true, 'max_file_size_mb' => '250', 'app_tagline' => 'Nouveau slogan', 'testimony_type_audio_enabled' => false,
        ])->assertOk()->assertJsonPath('data.app.maintenance_mode', true)->assertJsonPath('data.publishing.testimony_type_audio_enabled', false);

        $this->assertTrue(AppSetting::get('maintenance_mode'));
        $this->assertSame(250, AppSetting::get('max_file_size_mb'));
        $this->assertSame('Nouveau slogan', AppSetting::get('app_tagline'));

        $schema = $this->actingAs($admin)->getJson('/api/v1/admin/settings/schema')->json('data.groups');
        $this->assertSame(250, collect($schema)->flatMap(fn ($g) => $g['settings'])->firstWhere('key', 'max_file_size_mb')['value']);
    }

    public function test_put_rejects_unknown_hidden_or_mistyped_settings_and_saves_nothing(): void
    {
        $admin = $this->user('Admin', 'administrateur');

        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['maintenance_mode' => true, 'nope' => 1])
            ->assertStatus(422)->assertJsonValidationErrors(['nope']);
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['allow_audio' => false])
            ->assertStatus(422)->assertJsonValidationErrors(['allow_audio']);
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['max_file_size_mb' => 'beaucoup'])
            ->assertStatus(422)->assertJsonPath('errors.max_file_size_mb.0', 'Le paramètre max_file_size_mb doit être un nombre entier.');
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['maintenance_mode' => 'peut-être'])
            ->assertStatus(422)->assertJsonValidationErrors(['maintenance_mode']);
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['app_name' => ['a']])
            ->assertStatus(422)->assertJsonValidationErrors(['app_name']);

        $this->assertFalse(AppSetting::get('maintenance_mode'));
        $this->assertSame(100, AppSetting::get('max_file_size_mb'));
    }

    public function test_push_switch_is_listed_without_seeder_and_stops_every_push(): void
    {
        // Ligne créée par la migration (sans seeder) : affichée dans le groupe « notifications ».
        \App\Models\AppSetting::where('key', '!=', 'push_notifications_enabled')->where('group', '!=', 'publishing')->delete();
        $admin = $this->user('Admin', 'administrateur');
        $groups = $this->actingAs($admin)->getJson('/api/v1/admin/settings/schema')->assertOk()->json('data.groups');
        $this->assertSame(['publishing', 'notifications'], array_column($groups, 'key'));
        $this->assertSame('push_notifications_enabled', $groups[1]['settings'][0]['key']);
        $this->assertTrue(\App\Services\PushNotifications::globallyEnabled());

        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['push_notifications_enabled' => false])->assertOk();
        $this->assertFalse(\App\Services\PushNotifications::globallyEnabled());
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Notifications push')->assertSee('name="push_notifications_enabled"', false);
    }

    public function test_moderators_and_users_are_refused(): void
    {
        foreach (['moderateur', 'utilisateur'] as $role) {
            $user = $this->user(ucfirst($role), $role);
            $this->actingAs($user)->getJson('/api/v1/admin/settings/schema')->assertForbidden();
            $this->actingAs($user)->putJson('/api/v1/admin/settings', ['maintenance_mode' => true])->assertForbidden();
        }
        $this->assertFalse(AppSetting::get('maintenance_mode'));
    }

    public function test_web_form_uses_the_same_validation(): void
    {
        $admin = $this->user('Admin', 'administrateur');

        $this->actingAs($admin)->from('/admin/settings')->put('/admin/settings', ['max_file_size_mb' => 'abc', 'maintenance_mode' => '1'])
            ->assertRedirect('/admin/settings')->assertSessionHasErrors('max_file_size_mb');
        $this->assertFalse(AppSetting::get('maintenance_mode'));

        $this->actingAs($admin)->put('/admin/settings', ['max_file_size_mb' => '120', 'maintenance_mode' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(120, AppSetting::get('max_file_size_mb'));
        $this->assertTrue(AppSetting::get('maintenance_mode'));
    }
}

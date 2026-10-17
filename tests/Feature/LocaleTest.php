<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\DeviceToken;
use App\Models\Testimony;
use App\Models\User;
use App\Services\FcmClient;
use App\Services\TestimonyModeration;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Français et anglais : langue de l'API (Accept-Language), users.locale (PUT /me), push et
 * notifications dans la langue du destinataire, sélecteur de langue du site. Voir docs/fonctionnalites/langues.md
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'display_name' => 'Marie', 'email' => 'marie@example.com',
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    public function test_api_answers_in_french_by_default_and_in_english_with_accept_language(): void
    {
        $missing = '/api/v1/testimonies/00000000-0000-0000-0000-000000000000';

        $this->getJson($missing)->assertNotFound()
            ->assertJsonPath('message', 'Ressource introuvable')
            ->assertHeader('Content-Language', 'fr');

        $this->getJson($missing, ['Accept-Language' => 'en-US,en;q=0.9,fr;q=0.8'])->assertNotFound()
            ->assertJsonPath('message', 'Resource not found')
            ->assertHeader('Content-Language', 'en');

        // Langue non prise en charge : français ; poids q respectés.
        $this->getJson($missing, ['Accept-Language' => 'de-DE'])->assertJsonPath('message', 'Ressource introuvable');
        $this->getJson($missing, ['Accept-Language' => 'de;q=1, en;q=0.5, fr;q=0.2'])->assertJsonPath('message', 'Resource not found');
        $this->getJson($missing, ['Accept-Language' => 'en;q=0.3, fr;q=0.8'])->assertJsonPath('message', 'Ressource introuvable');
    }

    public function test_unauthenticated_and_validation_errors_follow_the_language(): void
    {
        $this->putJson('/api/v1/me', [], ['Accept-Language' => 'en'])->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');
        $this->putJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('message', 'Non authentifié');

        Sanctum::actingAs($this->user);
        $this->putJson('/api/v1/me', ['locale' => 'de'], ['Accept-Language' => 'en'])->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid data')
            ->assertJsonPath('errors.locale.0', 'The selected locale is invalid.');
        $this->putJson('/api/v1/me', ['locale' => 'de'])->assertUnprocessable()
            ->assertJsonPath('message', 'Données invalides')
            ->assertJsonPath('errors.locale.0', "La valeur choisie pour locale n'est pas valide.");
    }

    public function test_locale_is_saved_with_put_me_and_returned_by_the_profile(): void
    {
        $this->assertSame('fr', $this->user->fresh()->locale);
        Sanctum::actingAs($this->user);

        $this->putJson('/api/v1/me', ['locale' => 'en'])->assertOk()->assertJsonPath('data.locale', 'en');
        $this->assertSame('en', $this->user->fresh()->locale);
        $this->assertSame('en', $this->user->fresh()->preferredLocale());
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.locale', 'en');

        // Ancienne route : même règle.
        $this->putJson('/api/v1/users/me', ['locale' => 'fr'])->assertOk()->assertJsonPath('data.locale', 'fr');

        // Langue privée : absente du profil public.
        $other = User::create(['display_name' => 'Paul', 'email' => 'paul@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
        Sanctum::actingAs($other);
        $this->getJson("/api/v1/users/{$this->user->id}")->assertOk()->assertJsonPath('data.locale', null);
    }

    public function test_push_and_in_app_notification_use_the_recipient_language(): void
    {
        $this->fakeConfiguredFcm();
        Queue::fake();
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true, 'display_order' => 1]);
        $this->user->update(['locale' => 'en']);
        DeviceToken::create(['user_id' => $this->user->id, 'token' => 'jeton-en', 'platform' => 'android']);
        $moderator = User::create(['display_name' => 'Modé', 'email' => 'mod@example.com', 'password' => 'secret123', 'role' => 'moderateur', 'status' => 'active']);
        $testimony = Testimony::create([
            'user_id' => $this->user->id, 'title' => 'Ma guérison', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'pending',
        ]);

        app(TestimonyModeration::class)->approve($testimony, $moderator);

        Queue::assertPushed(SendPushNotificationJob::class, fn (SendPushNotificationJob $job) => $job->userId === $this->user->id
            && $job->title === 'Testimony approved'
            && $job->body === 'Your testimony "Ma guérison" has been approved'
            && $job->data['type'] === 'testimony_approved'
            && $job->data['testimony_id'] === $testimony->id);

        // Texte français enregistré ; liste rendue dans la langue de la requête.
        $notification = AppNotification::where('recipient_id', $this->user->id)->firstOrFail();
        $this->assertSame('Votre témoignage "Ma guérison" a été approuvé', $notification->getRawOriginal('message'));
        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/notifications', ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('data.0.message', 'Your testimony "Ma guérison" has been approved');
        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.message', 'Votre témoignage "Ma guérison" a été approuvé');
        // Langue de la requête rétablie après le push en anglais.
        $this->assertSame('fr', app()->getLocale());
    }

    public function test_notification_without_translation_key_keeps_its_stored_text(): void
    {
        $notification = AppNotification::create([
            'recipient_id' => $this->user->id, 'type' => 'comment', 'message' => 'Paul a commenté', 'created_at' => now(),
        ]);

        $this->assertSame('Paul a commenté', Locales::using('en', fn () => $notification->fresh()->message));
    }

    public function test_website_language_switcher_saves_session_and_account_locale(): void
    {
        $this->get('/')->assertOk()->assertSee('Découvrir')->assertSee('lang="fr"', false);

        $this->from('/')->post('/langue', ['locale' => 'en'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Discover')->assertSee('Past events')->assertSee('lang="en"', false);

        $this->actingAs($this->user)->from('/')->post('/langue', ['locale' => 'en'])->assertRedirect('/');
        $this->assertSame('en', $this->user->fresh()->locale);

        $this->post('/langue', ['locale' => 'es'])->assertSessionHasErrors('locale');
    }

    public function test_website_uses_the_account_language_without_a_session_choice(): void
    {
        $this->user->update(['locale' => 'en']);

        $this->actingAs($this->user)->get('/')->assertOk()->assertSee('My space')->assertDontSee('Mon espace');
    }

    /** FCM « configuré » (fichier de compte de service lisible) ; aucun envoi réel : la file est simulée. */
    private function fakeConfiguredFcm(): void
    {
        $dir = storage_path('framework/testing/fcm-locale');
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/sa.json", json_encode(['project_id' => 'testi-test', 'client_email' => 'push@testi-test.iam.gserviceaccount.com', 'private_key' => 'cle']));
        config(['services.fcm.credentials' => "{$dir}/sa.json", 'services.fcm.enabled' => null, 'services.fcm.delivery' => 'queue']);
        $this->app->forgetInstance(FcmClient::class);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
    }
}

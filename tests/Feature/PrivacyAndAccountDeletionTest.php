<?php

namespace Tests\Feature;

use App\Models\AccountDeletionRequest;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\Follow;
use App\Models\MediaFile;
use App\Models\PrayerRequest;
use App\Models\Prophecy;
use App\Models\Testimony;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\VerseStylePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Politique de confidentialité et suppression de compte, sans connexion (exigence Google Play) :
 * pages publiques FR/EN, API, suppression complète, demande sans compte, consentement à l'inscription.
 * Voir docs/fonctionnalites/confidentialite-et-suppression.md
 */
class PrivacyAndAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com", 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function superAdmin(): User
    {
        $admin = $this->user('super', ['role' => 'administrateur']);
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    // ── Pages publiques ─────────────────────────────────────────────────────

    public function test_privacy_pages_are_public_in_french_and_english(): void
    {
        $this->get('/confidentialite')->assertOk()
            ->assertSee('Politique de confidentialité de My Testimony')
            ->assertSee('Dernière mise à jour : 20 octobre 2026')
            ->assertSee('<table>', false) // tableaux Markdown rendus
            ->assertSee('Read in English')
            ->assertSee(route('legal.privacy.en', ['lang' => 'en']), false);

        $this->get('/privacy')->assertOk()
            ->assertSee('My Testimony Privacy Policy')
            ->assertSee('Last updated: October 20, 2026')
            ->assertSee('Bunny.net (BunnyWay d.o.o.)')
            ->assertSee('Lire en français');

        // Langue demandée explicitement, ou choisie avec le sélecteur de langue.
        $this->get('/confidentialite?lang=en')->assertOk()->assertSee('My Testimony Privacy Policy');
        $this->withSession(['locale' => 'en'])->get('/confidentialite')->assertOk()->assertSee('My Testimony Privacy Policy');
    }

    public function test_privacy_link_is_in_the_footer_and_on_login_and_register_pages(): void
    {
        $this->get('/')->assertOk()->assertSee(route('legal.privacy'), false)->assertSee('Politique de confidentialité');
        $this->get('/login')->assertOk()->assertSee(route('legal.privacy'), false);
        $this->get('/register')->assertOk()->assertSee(route('legal.privacy'), false)->assertSee('name="accepted_privacy"', false);
    }

    public function test_account_deletion_pages_are_public(): void
    {
        $this->get('/suppression-compte')->assertOk()
            ->assertSee('Supprimer votre compte My Testimony')
            ->assertSee('30 jours')
            ->assertSee('name="contact"', false);
        $this->get('/delete-account')->assertOk()
            ->assertSee('Delete your My Testimony account')
            ->assertSee('Make a request')->assertSee('Delete some data');
    }

    // ── API ─────────────────────────────────────────────────────────────────

    public function test_api_returns_the_policy_without_authentication(): void
    {
        $this->getJson('/api/v1/legal/privacy?lang=fr')->assertOk()
            ->assertJsonPath('data.title', 'Politique de confidentialité de My Testimony')
            ->assertJsonPath('data.updated_at', '2026-10-20')
            ->assertJsonPath('data.url', route('legal.privacy'))
            ->assertJsonStructure(['data' => ['title', 'updated_at', 'html', 'url']]);

        $en = $this->getJson('/api/v1/legal/privacy?lang=en')->assertOk()
            ->assertJsonPath('data.title', 'My Testimony Privacy Policy')
            ->assertJsonPath('data.url', route('legal.privacy.en'));
        $this->assertStringContainsString('<table>', $en->json('data.html'));
        $this->assertStringNotContainsString('<h1>', $en->json('data.html')); // titre renvoyé à part

        // Sans ?lang : Accept-Language, puis le français.
        $this->getJson('/api/v1/legal/privacy', ['Accept-Language' => 'en-US'])->assertJsonPath('data.title', 'My Testimony Privacy Policy');
        $this->getJson('/api/v1/legal/privacy')->assertJsonPath('data.title', 'Politique de confidentialité de My Testimony');
    }

    // ── Suppression complète ────────────────────────────────────────────────

    public function test_deleting_my_account_removes_all_data_files_and_bunny_videos(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['services.bunny_stream' => ['library_id' => '123', 'api_key' => 'cle', 'cdn_host' => 'cdn.example.com']]);
        Http::fake(['video.bunnycdn.com/*' => Http::response([], 200)]);

        $other = $this->user('autre');
        $user  = $this->user('membre', ['avatar_url' => '/storage/avatars/moi.jpg', 'following_count' => 1]);
        Storage::disk('public')->put('avatars/moi.jpg', 'x');
        Storage::disk('public')->put('media/audios/a.mp3', 'x');
        Storage::disk('public')->put('media/audios/a_64k.mp3', 'x');

        $audio = Testimony::create([
            'user_id' => $user->id, 'title' => 'Guérison', 'type' => 'audio', 'category_slug' => 'guerison',
            'media_url' => '/storage/media/audios/a.mp3', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);
        $video = Testimony::create([
            'user_id' => $user->id, 'title' => 'Vidéo', 'type' => 'video', 'category_slug' => 'guerison',
            'bunny_video_id' => 'guid-video', 'visibility' => 'public', 'status' => 'pending',
        ]);
        $trashed = Testimony::create([
            'user_id' => $user->id, 'title' => 'Corbeille', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved',
        ]);
        $trashed->delete();
        Storage::disk('local')->put("proofs/{$audio->id}/preuve.jpg", 'x');
        MediaFile::create([
            'user_id' => $user->id, 'disk' => 'public', 'path' => 'media/audios/a.mp3', 'url' => $audio->media_url,
            'mime_type' => 'audio/mpeg', 'type' => 'audio',
            'renditions' => [['disk' => 'public', 'path' => 'media/audios/a_64k.mp3', 'label' => '64k']],
        ]);
        PrayerRequest::create(['user_id' => $user->id, 'body' => 'Priez pour moi', 'visibility' => 'public', 'status' => 'open']);
        Prophecy::create(['user_id' => $user->id, 'body_text' => 'Parole', 'received_on' => now()->toDateString()]);
        UserSetting::create(['user_id' => $user->id]);
        VerseStylePreference::create(['user_id' => $user->id, 'font' => 'serif']);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'jeton-fcm', 'platform' => 'android']);
        Follow::create(['follower_id' => $user->id, 'following_id' => $other->id]);
        $other->forceFill(['follower_count' => 1])->save();
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Confirmation obligatoire : mot de passe.
        $this->withToken($token)->deleteJson('/api/v1/me')->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->withToken($token)->deleteJson('/api/v1/me', ['password' => 'mauvais'])->assertForbidden();
        $this->withToken($token)->deleteJson('/api/v1/me', ['password' => 'secret123'])->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        foreach (['testimonies', 'prayer_requests', 'prophecies', 'user_settings', 'verse_style_preferences', 'device_tokens', 'media_files'] as $table) {
            $this->assertDatabaseMissing($table, ['user_id' => $user->id]);
        }
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseMissing('testimonies', ['id' => $trashed->id]); // corbeille comprise
        $this->assertDatabaseMissing('follows', ['follower_id' => $user->id]);
        $this->assertSame(0, (int) $other->fresh()->follower_count);

        Storage::disk('public')->assertMissing('avatars/moi.jpg');
        Storage::disk('public')->assertMissing('media/audios/a.mp3');
        Storage::disk('public')->assertMissing('media/audios/a_64k.mp3');
        Storage::disk('local')->assertMissing("proofs/{$audio->id}/preuve.jpg");
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/videos/guid-video'));

        // Le jeton ne fonctionne plus.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.deleted', 'subject_id' => $user->id]);
    }

    public function test_account_without_password_is_deleted_with_a_confirmation_on_both_routes(): void
    {
        $phone = $this->user('telephone', ['password' => null, 'email' => null, 'phone' => '+22990000001']);
        $this->actingAs($phone)->deleteJson('/api/v1/me')->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->actingAs($phone)->deleteJson('/api/v1/me', ['confirm' => true])->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $phone->id]);

        $google = $this->user('google', ['password' => null]);
        $this->actingAs($google)->deleteJson('/api/v1/users/me', ['confirm' => true])->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $google->id]);
    }

    public function test_super_admins_cannot_delete_themselves(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->deleteJson('/api/v1/me', ['password' => 'secret123'])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $super->id, 'deleted_at' => null]);

        $this->actingAs($super)->get('/suppression-compte')->assertOk()->assertDontSee('id="account-delete-form"', false);
        $this->actingAs($super)->delete('/suppression-compte', ['password' => 'secret123'])->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $super->id]);
    }

    public function test_connected_member_deletes_the_account_from_the_public_page(): void
    {
        $user = $this->user('membre');

        $this->actingAs($user)->get('/suppression-compte')->assertOk()->assertSee('id="account-delete-form"', false);
        $this->actingAs($user)->from('/suppression-compte')->delete('/suppression-compte', ['password' => 'mauvais'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->actingAs($user)->delete('/suppression-compte', ['password' => 'secret123', 'lang' => 'fr'])
            ->assertRedirect(route('account-deletion', ['lang' => 'fr']))
            ->assertSessionHas('success');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // ── Demande sans connexion ──────────────────────────────────────────────

    public function test_guest_request_is_recorded_and_super_admins_are_notified(): void
    {
        $super  = $this->superAdmin();
        $member = $this->user('membre', ['phone' => '+22990000002']);

        $this->from('/suppression-compte')->post('/suppression-compte', ['type' => 'account', 'contact' => 'MEMBRE@example.com', 'message' => 'Merci'])
            ->assertRedirect('/suppression-compte')->assertSessionHas('success');

        $request = AccountDeletionRequest::firstOrFail();
        $this->assertSame('membre@example.com', $request->contact);
        $this->assertSame('email', $request->contact_type);
        $this->assertSame($member->id, $request->user_id);
        $this->assertSame('pending', $request->status);
        $this->assertTrue(AppNotification::where('recipient_id', $super->id)->where('type', 'account_deletion_requested')->exists());
        $this->assertFalse(AppNotification::where('recipient_id', $member->id)->exists());

        // Téléphone saisi librement ; contact inconnu : même réponse (aucune information sur les comptes existants).
        $this->post('/suppression-compte', ['type' => 'account', 'contact' => '00229 90 00 00 02'])->assertSessionHas('success');
        $this->assertSame($member->id, AccountDeletionRequest::where('contact', '+22990000002')->value('user_id'));
        $this->post('/suppression-compte', ['type' => 'account', 'contact' => 'inconnu@example.com'])->assertSessionHas('success');
        $this->assertNull(AccountDeletionRequest::where('contact', 'inconnu@example.com')->value('user_id'));

        // Le compte n'est pas supprimé tant qu'un super-administrateur n'a pas traité la demande.
        $this->assertDatabaseHas('users', ['id' => $member->id]);
    }

    public function test_guest_request_is_validated_protected_by_a_honeypot_and_throttled(): void
    {
        $this->from('/suppression-compte')->post('/suppression-compte', ['type' => 'account', 'contact' => 'pas un contact'])
            ->assertSessionHasErrors('contact');
        $this->post('/suppression-compte', ['type' => 'account', 'contact' => 'robot@example.com', 'website' => 'http://spam'])->assertSessionHas('success');
        $this->assertSame(0, AccountDeletionRequest::count());

        for ($i = 0; $i < 3; $i++) {
            $this->post('/suppression-compte', ['type' => 'account', 'contact' => "p{$i}@example.com"])->assertRedirect();
        }
        $this->post('/suppression-compte', ['type' => 'account', 'contact' => 'trop@example.com'])->assertStatus(429);
    }

    public function test_partial_deletion_section_and_request(): void
    {
        $super  = $this->superAdmin();
        $member = $this->user('membre');

        $this->get('/suppression-compte')->assertOk()
            ->assertSee('Supprimer certaines données sans supprimer votre compte')
            ->assertSee('menu « ⋮ »')
            ->assertSee('Les notifications se désactivent dans Paramètres.')
            ->assertSee('value="partial"', false)
            ->assertSee('value="account"', false);
        $this->get('/delete-account')->assertOk()->assertSee('Delete some data without deleting your account');

        // Suppression partielle : préciser les données est obligatoire.
        $this->from('/suppression-compte')->post('/suppression-compte', ['type' => 'partial', 'contact' => 'membre@example.com'])
            ->assertSessionHasErrors('message');
        $this->from('/suppression-compte')->post('/suppression-compte', ['type' => 'autre', 'contact' => 'membre@example.com'])
            ->assertSessionHasErrors('type');

        $this->post('/suppression-compte', ['type' => 'partial', 'contact' => 'membre@example.com', 'message' => 'Ma photo de profil'])
            ->assertSessionHas('success', __('privacy.deletion.partial_sent'));
        $request = AccountDeletionRequest::firstOrFail();
        $this->assertSame('partial', $request->type);
        $this->assertSame('Ma photo de profil', $request->message);
        $this->assertSame('privacy.deletion.notify_admin_partial_known',
            AppNotification::where('recipient_id', $super->id)->firstOrFail()->payload['i18n']['key']);

        // Jamais de suppression du compte depuis une demande partielle : seulement « Marquer comme traitée ».
        $this->actingAs($super)->get('/admin/demandes-suppression')->assertOk()->assertSee('Suppression partielle')->assertSee('Marquer comme traitée');
        $this->actingAs($super)->delete("/admin/demandes-suppression/{$request->id}/compte")->assertNotFound();
        $this->actingAs($super)->post("/admin/demandes-suppression/{$request->id}/traitee")->assertSessionHas('success');
        $this->assertSame('done', $request->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $member->id]);

        // Connecté : le formulaire reste proposé, suppression partielle cochée par défaut.
        $html = $this->actingAs($member)->get('/suppression-compte')->assertOk()
            ->assertSee('id="account-delete-form"', false)->getContent();
        $this->assertMatchesRegularExpression('/value="partial"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="account"[^>]*checked/', $html);
    }

    public function test_super_admin_deletes_the_account_of_a_request(): void
    {
        $super  = $this->superAdmin();
        $admin  = $this->user('admin', ['role' => 'administrateur']);
        $member = $this->user('membre');
        $this->post('/suppression-compte', ['type' => 'account', 'contact' => 'membre@example.com']);
        $request = AccountDeletionRequest::firstOrFail();

        $this->actingAs($admin)->get('/admin/demandes-suppression')->assertOk()->assertSee('membre@example.com');
        $this->actingAs($admin)->delete("/admin/demandes-suppression/{$request->id}/compte")->assertForbidden();

        $this->actingAs($super)->delete("/admin/demandes-suppression/{$request->id}/compte")->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertSame('done', $request->fresh()->status);
        $this->assertSame($super->id, $request->fresh()->handled_by);
    }

    // ── Consentement à l'inscription ───────────────────────────────────────

    public function test_registration_requires_accepting_the_privacy_policy(): void
    {
        $payload = ['account_type' => 'individual', 'display_name' => 'Jean', 'email' => 'jean@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123'];

        $this->from('/register')->post('/register', $payload)->assertSessionHasErrors('accepted_privacy');
        $this->post('/register', $payload + ['accepted_privacy' => '1'])->assertSessionHasNoErrors();
        $this->assertNotNull(User::where('email', 'jean@example.com')->value('accepted_privacy_at'));
        auth()->logout();

        $api = ['display_name' => 'Awa', 'email' => 'awa@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'];
        $this->postJson('/api/v1/auth/register', $api)->assertUnprocessable()->assertJsonValidationErrors('accepted_privacy');
        $this->postJson('/api/v1/auth/register', $api + ['accepted_privacy' => true])->assertCreated()
            ->assertJsonPath('data.user.accepted_privacy_at', fn ($v) => is_string($v) && $v !== '');
    }
}

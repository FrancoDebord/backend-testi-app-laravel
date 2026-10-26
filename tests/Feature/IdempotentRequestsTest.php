<?php

namespace Tests\Feature;

use App\Models\AccountDelegateAction;
use App\Models\MediaFile;
use App\Models\PrayerRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Écritures rejouées par la file hors ligne de l'application (en-tête Idempotency-Key).
 * Voir docs/fonctionnalites/hors-connexion.md
 */
class IdempotentRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name): User
    {
        return User::create([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    private function send(User $user, ?string $key, string $body = 'Priez pour mon examen de vendredi, merci beaucoup.')
    {
        return $this->actingAs($user)->postJson('/api/v1/prayer/requests', ['body' => $body],
            $key === null ? [] : ['Idempotency-Key' => $key]);
    }

    public function test_une_ecriture_rejouee_avec_la_meme_cle_n_est_faite_qu_une_fois(): void
    {
        $user = $this->makeUser('auteur');

        $first = $this->send($user, 'outbox-0001-abcdef')->assertCreated();
        $second = $this->send($user, 'outbox-0001-abcdef')->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, PrayerRequest::count());
    }

    public function test_sans_cle_ou_avec_une_autre_cle_chaque_envoi_compte(): void
    {
        $user = $this->makeUser('auteur');

        $this->send($user, null)->assertCreated();
        $this->send($user, null)->assertCreated();
        $this->send($user, 'outbox-0002-abcdef')->assertCreated();
        $this->send($user, 'outbox-0003-abcdef')->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(4, PrayerRequest::count());
    }

    public function test_la_cle_est_propre_a_chaque_personne(): void
    {
        $this->send($this->makeUser('un'), 'outbox-0004-abcdef')->assertCreated();
        $this->send($this->makeUser('deux'), 'outbox-0004-abcdef')->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(2, PrayerRequest::count());
    }

    public function test_un_refus_n_est_pas_memorise(): void
    {
        $user = $this->makeUser('auteur');

        $this->send($user, 'outbox-0005-abcdef', '')->assertStatus(422);
        $this->send($user, 'outbox-0005-abcdef')->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(1, PrayerRequest::count());
    }

    public function test_avec_un_jeton_comme_l_application(): void
    {
        $user = $this->makeUser('auteur');
        $token = $user->createToken('mobile')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Idempotency-Key' => 'outbox-0006-abcdef'];
        $body = ['body' => 'Priez pour mon examen de vendredi, merci beaucoup.'];

        $this->postJson('/api/v1/prayer/requests', $body, $headers)->assertCreated();
        $this->postJson('/api/v1/prayer/requests', $body, $headers)->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(1, PrayerRequest::count());
    }

    public function test_au_nom_d_un_compte_administre_le_renvoi_n_est_pas_inscrit_deux_fois_au_journal(): void
    {
        $owner = $this->makeUser('pasteur');
        $marie = $this->makeUser('marie');
        $this->actingAs($owner)->postJson('/api/v1/users/me/managers', ['user_id' => $marie->id])->assertOk();

        foreach ([1, 2] as $_) {
            $this->actingAs($marie)->withHeader('X-Act-As', $owner->id)
                ->postJson('/api/v1/prayer/requests', ['body' => 'Priez pour notre campagne de samedi prochain.'],
                    ['Idempotency-Key' => 'outbox-0007-abcdef'])
                ->assertCreated()->assertHeader('X-Acting-As', $owner->id);
        }

        $this->assertSame(1, PrayerRequest::where('user_id', $owner->id)->count());
        $this->assertSame(1, AccountDelegateAction::where('account_id', $owner->id)->where('actor_id', $marie->id)->count());
    }

    public function test_un_fichier_renvoye_n_est_enregistre_qu_une_fois(): void
    {
        Storage::fake('public');
        $user = $this->makeUser('auteur');

        $send = fn () => $this->actingAs($user)->post('/api/v1/media/upload',
            ['file' => UploadedFile::fake()->image('couverture.jpg')],
            ['Accept' => 'application/json', 'Idempotency-Key' => 'outbox-0008-abcdef-b1']);

        $url = $send()->assertCreated()->json('data.url');
        $this->assertSame($url, $send()->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->json('data.url'));

        $this->assertSame(1, MediaFile::count());
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }
}

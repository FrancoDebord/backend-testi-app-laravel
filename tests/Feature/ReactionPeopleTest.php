<?php

namespace Tests\Feature;

use App\Models\ContentReaction;
use App\Models\DailyVerse;
use App\Models\DailyVerseReaction;
use App\Models\PrayerRequest;
use App\Models\PrayerRequestPrayer;
use App\Models\Reaction;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Qui a réagi ? » : effectif de chaque réaction et personnes. docs/fonctionnalites/qui-a-reagi.md
 */
class ReactionPeopleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function testimony(User $author, string $visibility = 'public'): Testimony
    {
        return Testimony::create([
            'user_id' => $author->id, 'title' => 'Guérison', 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'Texte', 'visibility' => $visibility, 'status' => 'approved', 'approved_at' => now(),
        ]);
    }

    private function react(User $user, Testimony $t, string $type, int $minutesAgo = 0): void
    {
        // created_at hors de $fillable : forceFill.
        (new Reaction)->forceFill(['user_id' => $user->id, 'testimony_id' => $t->id, 'type' => $type, 'created_at' => now()->subMinutes($minutesAgo)])->save();
    }

    public function test_effectif_de_chaque_reaction_et_personnes(): void
    {
        $t = $this->testimony($this->user('Auteur'));
        [$ama, $kofi, $afi, $banni] = [$this->user('Ama'), $this->user('Kofi'), $this->user('Afi'), $this->user('Banni', ['status' => 'suspended'])];
        $this->react($ama, $t, 'like', 30);
        $this->react($ama, $t, 'pray', 20);
        $this->react($kofi, $t, 'fire', 10);
        $this->react($afi, $t, 'pray', 5);
        $this->react($banni, $t, 'like');     // compte suspendu : ni compté ni listé

        $res = $this->getJson("/api/v1/testimonies/{$t->id}/reactions/people")->assertOk()
            ->assertJsonPath('data.summary.total', 3)
            ->assertJsonPath('data.summary.likesCount', 2)
            ->assertJsonPath('data.summary.prayersCount', 2)
            ->assertJsonPath('meta.total', 3);
        $this->assertSame(
            [['like', 1], ['pray', 2], ['fire', 1]],
            array_map(fn ($t) => [$t['type'], $t['count']], $res->json('data.summary.types')),
        );
        // La plus récente d'abord ; une ligne par personne avec toutes ses réactions.
        $this->assertSame(['Afi', 'Kofi', 'Ama'], array_column($res->json('data.people'), 'displayName'));
        $this->assertSame(['like', 'pray'], $res->json('data.people.2.reactions'));

        // Une réaction : seulement ces personnes ; un groupe : toutes ses réactions.
        $this->assertSame(['Afi', 'Ama'], array_column($this->getJson("/api/v1/testimonies/{$t->id}/reactions/people?type=pray")->json('data.people'), 'displayName'));
        $this->assertSame(['Kofi', 'Ama'], array_column($this->getJson("/api/v1/testimonies/{$t->id}/reactions/people?type=appreciation")->json('data.people'), 'displayName'));
        $this->getJson("/api/v1/testimonies/{$t->id}/reactions/people?type=inconnu")->assertStatus(422);
    }

    public function test_pagination_et_publication_privee(): void
    {
        $author = $this->user('Auteur');
        $t = $this->testimony($author);
        foreach (range(1, 32) as $i) $this->react($this->user("Personne {$i}"), $t, 'like', $i);

        $this->getJson("/api/v1/testimonies/{$t->id}/reactions/people")->assertJsonCount(30, 'data.people')->assertJsonPath('meta.lastPage', 2);
        $this->getJson("/api/v1/testimonies/{$t->id}/reactions/people?page=2")->assertJsonCount(2, 'data.people')
            ->assertJsonPath('data.people.1.displayName', 'Personne 32');

        // Publication que la personne ne peut pas voir : 404.
        $private = $this->testimony($author, 'private');
        $this->getJson("/api/v1/testimonies/{$private->id}/reactions/people")->assertNotFound();
        $this->get("/reactions/testimony/{$private->id}")->assertNotFound();
    }

    public function test_requete_de_priere_le_je_prie_dedie_est_compte(): void
    {
        $author = $this->user('Auteur');
        $prayer = PrayerRequest::create(['user_id' => $author->id, 'body' => 'Pour ma famille', 'visibility' => 'public']);
        $ama = $this->user('Ama');
        $kofi = $this->user('Kofi');
        PrayerRequestPrayer::create(['prayer_request_id' => $prayer->id, 'user_id' => $ama->id]);
        ContentReaction::create(['user_id' => $kofi->id, 'subject_type' => 'prayer_request', 'subject_id' => $prayer->id, 'type' => 'love']);

        $this->getJson("/api/v1/prayer/requests/{$prayer->id}/reactions/people")->assertOk()
            ->assertJsonPath('data.summary.prayersCount', 1)
            ->assertJsonPath('data.summary.likesCount', 1)
            ->assertJsonPath('data.summary.types.0.type', 'love');
        $this->getJson("/api/v1/prayer/requests/{$prayer->id}/reactions/people?type=pray")
            ->assertJsonPath('data.people.0.displayName', 'Ama')->assertJsonCount(1, 'data.people');
    }

    public function test_onglet_commentaires(): void
    {
        $t = $this->testimony($this->user('Auteur'));
        $ama = $this->user('Ama');
        $this->react($ama, $t, 'like');
        \App\Models\Comment::create(['testimony_id' => $t->id, 'user_id' => $ama->id, 'body' => 'Gloire à Dieu pour toi']);
        \App\Models\Comment::create(['testimony_id' => $t->id, 'user_id' => $this->user('Banni', ['status' => 'suspended'])->id, 'body' => 'Message masqué']);

        // Onglet avec l'effectif (comptes actifs seulement), puis qui a commenté et quoi.
        $this->get("/reactions/testimony/{$t->id}")->assertOk()->assertSeeInOrder(['Toutes', 'Commentaires', '1', "J'aime"], false)
            ->assertDontSee('overflow-x-auto', false);
        $this->get("/reactions/testimony/{$t->id}?type=comments")->assertOk()
            ->assertSee('Ama')->assertSee('Gloire à Dieu pour toi')->assertDontSee('Message masqué');
        $this->getJson("/api/v1/testimonies/{$t->id}/reactions/people?type=comments")->assertOk()
            ->assertJsonPath('data.summary.commentsCount', 1)
            ->assertJsonPath('data.comments.0.body', 'Gloire à Dieu pour toi')
            ->assertJsonPath('data.comments.0.user.displayName', 'Ama');

        // Requête de prière : ses encouragements.
        $prayer = PrayerRequest::create(['user_id' => $ama->id, 'body' => 'Pour ma famille', 'visibility' => 'public']);
        \App\Models\PrayerRequestMessage::create(['prayer_request_id' => $prayer->id, 'user_id' => $this->user('Kofi')->id, 'body' => 'Courage, Dieu est fidèle']);
        $this->get("/reactions/prayer_request/{$prayer->id}")->assertOk()->assertSee('Encouragements');
        $this->get("/reactions/prayer_request/{$prayer->id}?type=comments")->assertOk()->assertSee('Courage, Dieu est fidèle');

        // Témoignage sans commentaire : l'onglet est quand même là (« Commentaires 0 »).
        $other = $this->testimony($ama);
        $this->get("/reactions/testimony/{$other->id}")->assertOk()->assertSee('?type=comments', false);
        $this->get("/reactions/testimony/{$other->id}?type=comments")->assertOk()->assertSee('Aucun commentaire pour le moment.');
    }

    public function test_verset_du_jour(): void
    {
        $verse = DailyVerse::create(['verse_text' => 'Car Dieu a tant aimé le monde', 'reference' => 'Jean 3:16', 'scheduled_date' => now()->toDateString(), 'is_active' => true]);
        DailyVerseReaction::create(['daily_verse_id' => $verse->id, 'user_id' => $this->user('Ama')->id, 'type' => 'like']);

        $this->getJson("/api/v1/daily-verse/{$verse->id}/reactions/people")->assertOk()
            ->assertJsonPath('data.summary.likesCount', 1)->assertJsonPath('data.people.0.displayName', 'Ama');
        $this->get("/reactions/daily_verse/{$verse->id}")->assertOk()->assertSee('Ama');
    }

    public function test_popup_du_site(): void
    {
        $t = $this->testimony($this->user('Auteur'));
        $this->react($this->user('Ama'), $t, 'like', 2);
        $this->react($this->user('Kofi'), $t, 'pray', 1);

        // Bouton sur la page du témoignage, popup dans la mise en page.
        $this->get("/testimonies/{$t->id}")->assertOk()
            ->assertSee('data-reaction-people="' . route('reactions.people', ['kind' => 'testimony', 'id' => $t->id]) . '"', false)
            ->assertSee('id="reaction-people-modal"', false);

        // Onglets avec effectif, puis les personnes.
        $this->get("/reactions/testimony/{$t->id}")->assertOk()
            ->assertSeeInOrder(['Toutes', '2', "J'aime", '1', 'Je prie', '1'], false)
            ->assertSeeInOrder(['Kofi', 'Ama']);
        $this->get("/reactions/testimony/{$t->id}?type=pray")->assertOk()->assertSee('Kofi')->assertDontSee('>Ama<', false);
        $this->get("/reactions/testimony/{$t->id}?type=love")->assertOk()->assertSee('Personne pour cette réaction.');
        $this->get("/reactions/testimony/{$t->id}?type=xyz")->assertNotFound();
    }
}

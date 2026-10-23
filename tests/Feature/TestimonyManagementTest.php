<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\Reaction;
use App\Models\Testimony;
use App\Models\TestimonyDailyStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Gestion des témoignages : modération, visibilité, « Modifier et renvoyer », label, mise à la une,
 * filtres, corbeille, statistiques, back-office, permissions, super-administrateur.
 * Voir docs/fonctionnalites/gestion-des-temoignages.md
 */
class TestimonyManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $reader;
    private User $modo;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->author = $this->makeUser('auteur');
        $this->reader = $this->makeUser('lecteur');
        $this->modo   = $this->makeUser('modo', ['role' => 'moderateur']);
        $this->admin  = $this->makeUser('admin', ['role' => 'administrateur']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function testimony(string $status = 'approved', array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $this->author->id, 'title' => 'Guérison ' . uniqid(), 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Dieu m\'a guéri.', 'visibility' => 'public', 'status' => $status,
            'approved_at' => $status === 'approved' ? now() : null,
        ], $attrs));
    }

    private function superAdmin(): User
    {
        $super = $this->makeUser('super', ['role' => 'administrateur']);
        $super->forceFill(['is_super_admin' => true])->save();

        return $super;
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    private function guest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    // ─── Visibilité selon le statut ──────────────────────────────────────────

    public function test_only_approved_testimonies_are_public_and_author_sees_own_with_status(): void
    {
        $pending = $this->testimony('pending');
        $rejected = $this->testimony('rejected', ['rejection_reason' => 'Spam']);
        $approved = $this->testimony('approved');

        $ids = collect($this->guest()->getJson('/api/v1/testimonies')->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$approved->id], $ids->all());

        $this->guest()->getJson("/api/v1/testimonies/{$pending->id}")->assertNotFound();
        $this->as($this->reader)->getJson("/api/v1/testimonies/{$rejected->id}")->assertNotFound();
        $this->as($this->author)->getJson("/api/v1/testimonies/{$rejected->id}")->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejectionReason', 'Spam');
        $this->as($this->modo)->getJson("/api/v1/testimonies/{$pending->id}")->assertOk();

        $mine = collect($this->as($this->author)->getJson('/api/v1/testimonies/my')->assertOk()->json('data'));
        $this->assertCount(3, $mine);
        $this->assertEqualsCanonicalizing(['pending', 'rejected', 'approved'], $mine->pluck('status')->all());
    }

    public function test_without_required_moderation_new_testimonies_are_approved_at_creation(): void
    {
        config(['testimonies.require_moderation' => false]);

        $id = $this->as($this->author)->postJson('/api/v1/testimonies', [
            'title' => 'Publié directement', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Merci Seigneur pour ta fidélité.',
        ])->assertCreated()->assertJsonPath('data.status', 'approved')->json('data.id');

        $t = Testimony::find($id);
        $this->assertNull($t->moderated_by);
        $this->assertEquals($t->created_at->toDateTimeString(), $t->moderated_at->toDateTimeString());
        $this->assertSame(1, $this->author->fresh()->testimony_count);
        $this->guest()->getJson('/api/v1/testimonies')->assertJsonPath('data.0.id', $id);
    }

    // ─── Approuver, refuser ──────────────────────────────────────────────────

    public function test_approve_and_reject_record_moderation_fields_audit_and_notify_author(): void
    {
        $t = $this->testimony('pending');

        $this->as($this->modo)->postJson("/api/v1/moderation/{$t->id}/approve")->assertOk();
        $t->refresh();
        $this->assertSame('approved', $t->status->value);
        $this->assertSame($this->modo->id, $t->moderated_by);
        $this->assertNotNull($t->moderated_at);
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $this->author->id, 'type' => 'testimony_approved']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'testimony.approved', 'subject_id' => $t->id, 'actor_id' => $this->modo->id]);

        // Motif obligatoire.
        $this->as($this->modo)->postJson("/api/v1/moderation/{$t->id}/reject", [])->assertUnprocessable();

        $this->as($this->modo)->postJson("/api/v1/moderation/{$t->id}/reject", ['rejection_reason' => 'Le texte contient des coordonnées personnelles.'])->assertOk();
        $t->refresh();
        $this->assertSame('rejected', $t->status->value);
        $this->assertSame('Le texte contient des coordonnées personnelles.', $t->rejection_reason);
        $notification = AppNotification::where('type', 'testimony_rejected')->firstOrFail();
        $this->assertStringContainsString('coordonnées personnelles', $notification->message);
        $this->assertSame('Le texte contient des coordonnées personnelles.', $notification->payload['rejection_reason']);
        $this->assertSame(0, $this->author->fresh()->testimony_count); // dépublié

        $log = AdminAuditLog::where('action', 'testimony.rejected')->firstOrFail();
        $this->assertSame('approved', $log->before['status']);
        $this->assertSame('rejected', $log->after['status']);

        // Code de motif seul : son libellé sert de motif.
        $other = $this->testimony('pending');
        $this->as($this->modo)->postJson("/api/v1/moderation/{$other->id}/reject", ['reason' => 'spam'])->assertOk();
        $this->assertSame('Spam', $other->fresh()->rejection_reason);
    }

    // ─── « Modifier et renvoyer » ────────────────────────────────────────────

    public function test_author_editing_a_rejected_testimony_sends_it_back_to_moderation(): void
    {
        $t = $this->testimony('rejected', ['rejection_reason' => 'Trop court', 'moderated_by' => $this->modo->id, 'moderated_at' => now()]);

        $this->as($this->author)->putJson("/api/v1/testimonies/{$t->id}", ['body_text' => 'Texte complété avec plus de détails.'])
            ->assertOk()->assertJsonPath('data.status', 'pending');

        $t->refresh();
        $this->assertNull($t->rejection_reason);
        $this->assertNull($t->moderated_by);
        $this->assertNull($t->moderated_at);
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $this->modo->id, 'type' => 'testimony_resubmitted', 'testimony_id' => $t->id]);
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $this->admin->id, 'type' => 'testimony_resubmitted']);
        $this->assertDatabaseMissing('app_notifications', ['recipient_id' => $this->author->id, 'type' => 'testimony_resubmitted']);
    }

    public function test_author_editing_an_approved_testimony_follows_moderation_setting_and_loses_label(): void
    {
        $t = $this->testimony('approved', ['is_verified' => true, 'verified_by' => $this->admin->id, 'verified_at' => now()]);
        $this->author->forceFill(['testimony_count' => 1])->save();

        $this->as($this->author)->putJson("/api/v1/testimonies/{$t->id}", ['title' => 'Nouveau titre'])
            ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.isVerified', false);
        $t->refresh();
        $this->assertFalse($t->is_verified);
        $this->assertNull($t->verified_by);
        $this->assertSame(0, $this->author->fresh()->testimony_count);

        // Modération non obligatoire : reste publié, mais le label est retiré.
        config(['testimonies.require_moderation' => false]);
        $t2 = $this->testimony('approved', ['is_verified' => true, 'verified_by' => $this->admin->id, 'verified_at' => now()]);
        $this->as($this->author)->putJson("/api/v1/testimonies/{$t2->id}", ['title' => 'Autre titre'])
            ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.badges', []);
        $this->assertFalse($t2->fresh()->is_verified);
        $this->assertDatabaseMissing('app_notifications', ['type' => 'testimony_resubmitted', 'testimony_id' => $t2->id]);
    }

    // ─── Label et badges ─────────────────────────────────────────────────────

    public function test_only_admins_grant_the_verified_label_and_badges_are_exposed(): void
    {
        $t = $this->testimony('approved');

        $this->as($this->modo)->post("/moderation/{$t->id}/label", ['verified' => 1])->assertForbidden();
        $this->as($this->admin)->post("/moderation/{$t->id}/label", ['verified' => 1])->assertRedirect();

        $this->guest()->getJson("/api/v1/testimonies/{$t->id}")->assertOk()
            ->assertJsonPath('data.isVerified', true)
            ->assertJsonPath('data.badges', ['verified']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'testimony.verified', 'actor_id' => $this->admin->id]);

        // Un témoignage non publié ne reçoit pas le label.
        $pending = $this->testimony('pending');
        $this->as($this->admin)->post("/moderation/{$pending->id}/label", ['verified' => 1])->assertSessionHas('error');
        $this->assertFalse($pending->fresh()->is_verified);
    }

    // ─── À la une ────────────────────────────────────────────────────────────

    public function test_featured_endpoint_includes_newest_orders_by_publication_and_fills_with_recent(): void
    {
        $mOld   = $this->testimony('approved', ['is_featured' => true, 'featured_order' => 1, 'approved_at' => now()->subDays(5)]);
        $mMid   = $this->testimony('approved', ['is_featured' => true, 'featured_order' => 2, 'approved_at' => now()->subDays(2)]);
        $future = $this->testimony('approved', ['is_featured' => true, 'featured_order' => 0, 'featured_from' => now()->addDay(), 'approved_at' => now()->subDays(3)]);
        $ended  = $this->testimony('approved', ['is_featured' => true, 'featured_order' => 0, 'featured_until' => now()->subDay(), 'approved_at' => now()->subDays(4)]);
        $recent = $this->testimony('approved', ['approved_at' => now()->subHour()]);
        $this->testimony('pending'); // jamais

        $data = collect($this->guest()->getJson('/api/v1/testimonies/featured')->assertOk()->json('data'));
        // Du plus récemment publié au plus ancien, manuels et compléments mêlés ; le plus récent en tête.
        $this->assertSame([$recent->id, $mMid->id, $future->id, $ended->id, $mOld->id], $data->pluck('id')->all());
        $this->assertSame(['recent', 'manual', 'recent', 'recent', 'manual'], $data->pluck('featured_source')->all());
        $this->assertSame(['featured'], $data->firstWhere('id', $mMid->id)['badges']);
        $this->assertSame([], $data->firstWhere('id', $recent->id)['badges']);
        $this->assertNotContains('featured', $data->where('featured_source', 'recent')->pluck('badges')->flatten()->all());

        // Manuel strict : le plus récent entre quand même.
        config(['testimonies.featured_fill_recent' => false]);
        Cache::flush();
        $strict = collect($this->guest()->getJson('/api/v1/testimonies/featured')->json('data'));
        $this->assertSame([$recent->id, $mMid->id, $mOld->id], $strict->pluck('id')->all());

        // Assez de manuels pour la limite : le plus récent prend la première place.
        config(['testimonies.featured_limit' => 2]);
        Cache::flush();
        $limited = collect($this->guest()->getJson('/api/v1/testimonies/featured')->json('data'));
        $this->assertSame([$recent->id, $mMid->id], $limited->pluck('id')->all());

        // ?featured=1 sur la liste : mises à la une manuelles actives seulement.
        $ids = collect($this->guest()->getJson('/api/v1/testimonies?featured=1')->json('data'))->pluck('id');
        $this->assertEqualsCanonicalizing([$mOld->id, $mMid->id], $ids->all());
    }

    public function test_newest_testimony_manually_featured_keeps_manual_source(): void
    {
        $older  = $this->testimony('approved', ['approved_at' => now()->subDay()]);
        $newest = $this->testimony('approved', ['is_featured' => true, 'approved_at' => now()->subMinute()]);

        $data = collect($this->guest()->getJson('/api/v1/testimonies/featured')->json('data'));
        $this->assertSame([$newest->id, $older->id], $data->pluck('id')->all());
        $this->assertSame(['manual', 'recent'], $data->pluck('featured_source')->all());
    }

    public function test_approved_and_deleted_testimonies_update_the_featured_list_right_away(): void
    {
        $old = $this->testimony('approved', ['is_featured' => true, 'approved_at' => now()->subDays(3)]);
        config(['testimonies.featured_fill_recent' => false, 'testimonies.featured_limit' => 1]);
        $this->guest()->getJson('/api/v1/testimonies/featured')->assertJsonPath('data.0.id', $old->id);

        // Approbation : le nouveau témoignage apparaît aussitôt (cache vidé).
        $pending = $this->testimony('pending');
        $this->as($this->modo)->postJson("/api/v1/moderation/{$pending->id}/approve")->assertOk();
        $this->guest()->getJson('/api/v1/testimonies/featured')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)->assertJsonPath('data.0.featured_source', 'recent');

        // Suppression : il disparaît aussitôt.
        $pending->fresh()->delete();
        $this->guest()->getJson('/api/v1/testimonies/featured')->assertJsonPath('data.0.id', $old->id);
    }

    public function test_featured_list_is_cached_and_refreshed_when_featuring(): void
    {
        $t = $this->testimony('approved');
        config(['testimonies.featured_fill_recent' => false]);
        // Le plus récent publié est toujours à la une (source « recent » tant qu'il n'est pas mis à la une).
        $this->guest()->getJson('/api/v1/testimonies/featured')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.featured_source', 'recent');

        $this->as($this->modo)->post("/moderation/{$t->id}/une", ['featured' => 1, 'featured_order' => 3])->assertRedirect();
        $this->guest()->getJson('/api/v1/testimonies/featured')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.featured_source', 'manual')
            ->assertJsonPath('data.0.featuredOrder', 3);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'testimony.featured', 'actor_id' => $this->modo->id]);
    }

    // ─── Filtres ─────────────────────────────────────────────────────────────

    public function test_list_filters_are_combinable_and_unpublished_statuses_are_reserved(): void
    {
        $popular  = $this->testimony('approved', ['like_count' => 50, 'views_count' => 10, 'is_verified' => true]);
        $viewed   = $this->testimony('approved', ['like_count' => 1, 'views_count' => 900, 'is_verified' => true]);
        $plain    = $this->testimony('approved', ['like_count' => 5, 'views_count' => 5]);
        $pending  = $this->testimony('pending');

        $this->assertSame([$popular->id, $plain->id, $viewed->id],
            collect($this->guest()->getJson('/api/v1/testimonies?sort=popular')->json('data'))->pluck('id')->all());
        $this->assertSame([$viewed->id, $popular->id],
            collect($this->guest()->getJson('/api/v1/testimonies?verified=1&sort=most_viewed')->json('data'))->pluck('id')->all());
        $this->assertSame([$viewed->id], collect($this->guest()->getJson('/api/v1/testimonies?status=approved&verified=1&sort=most_viewed&limit=1')->json('data'))->pluck('id')->all());

        $this->guest()->getJson('/api/v1/testimonies?status=pending')->assertForbidden();
        $this->as($this->reader)->getJson('/api/v1/testimonies?status=pending')->assertForbidden();
        $this->as($this->modo)->getJson('/api/v1/testimonies?status=pending')->assertOk()->assertJsonPath('data.0.id', $pending->id);
        $this->guest()->getJson('/api/v1/testimonies?status=deleted')->assertUnprocessable();
    }

    // ─── Corbeille ───────────────────────────────────────────────────────────

    public function test_trash_restore_within_30_days_and_permanent_deletion_rights(): void
    {
        $t = $this->testimony('approved');

        $this->as($this->modo)->delete("/moderation/{$t->id}")->assertRedirect();
        $this->assertSoftDeleted($t);
        $this->guest()->getJson("/api/v1/testimonies/{$t->id}")->assertNotFound();

        $this->as($this->modo)->post("/moderation/{$t->id}/restaurer")->assertRedirect();
        $this->assertNotSoftDeleted($t);

        $t->delete();
        // Suppression définitive : ni modérateur, ni administrateur ordinaire.
        $this->as($this->modo)->delete("/moderation/{$t->id}/definitif")->assertForbidden();
        $this->as($this->admin)->delete("/moderation/{$t->id}/definitif")->assertForbidden();
        $this->assertSoftDeleted($t);

        // Au-delà de 30 jours : plus restaurable, puis purgé par la commande planifiée.
        Testimony::withTrashed()->whereKey($t->id)->update(['deleted_at' => now()->subDays(31)]);
        $this->as($this->modo)->post("/moderation/{$t->id}/restaurer")->assertSessionHas('error');
        $this->assertSoftDeleted($t);
        $recent = $this->testimony('approved');
        $recent->delete();

        $this->artisan('testimonies:purge-trash')->assertSuccessful();
        $this->assertDatabaseMissing('testimonies', ['id' => $t->id]);
        $this->assertSoftDeleted($recent);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'testimony.force_deleted', 'subject_id' => $t->id, 'actor_id' => null]);

        // Super-administrateur : suppression définitive immédiate.
        $this->as($this->superAdmin())->delete("/moderation/{$recent->id}/definitif")->assertRedirect();
        $this->assertDatabaseMissing('testimonies', ['id' => $recent->id]);
    }

    public function test_trash_tab_offers_permanent_deletion_to_super_admin_only(): void
    {
        $trashed = $this->testimony('approved', ['title' => 'Dans la corbeille']);
        $trashed->delete();
        $live = $this->testimony('approved', ['title' => 'Toujours public']);
        $super = $this->superAdmin();

        // Bouton par ligne et action groupée : super-administrateur seulement.
        $this->as($super)->get('/moderation?onglet=trash')->assertOk()
            ->assertSee(route('moderation.force-delete', $trashed->id))->assertSee('value="force_delete"', false);
        $this->as($this->admin)->get('/moderation?onglet=trash')->assertOk()
            ->assertDontSee(route('moderation.force-delete', $trashed->id))->assertDontSee('value="force_delete"', false);

        // Action groupée refusée à un administrateur ordinaire : rien n'est supprimé.
        $this->as($this->admin)->post('/moderation/actions-groupees', ['ids' => [$trashed->id], 'action' => 'force_delete'])->assertRedirect();
        $this->assertSoftDeleted($trashed);

        // Super-administrateur : seuls les témoignages de la corbeille sont supprimés.
        $this->as($super)->post('/moderation/actions-groupees', ['ids' => [$trashed->id, $live->id], 'action' => 'force_delete'])->assertRedirect();
        $this->assertDatabaseMissing('testimonies', ['id' => $trashed->id]);
        $this->assertNotSoftDeleted($live);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'testimony.force_deleted', 'subject_id' => $trashed->id, 'actor_id' => $super->id]);
    }

    // ─── Statistiques ────────────────────────────────────────────────────────

    public function test_views_are_deduplicated_per_person_or_device_for_30_minutes(): void
    {
        $t = $this->testimony('approved');

        $this->as($this->reader)->postJson("/api/v1/testimonies/{$t->id}/view")->assertOk()->assertJsonPath('data.counted', true);
        $this->as($this->reader)->postJson("/api/v1/testimonies/{$t->id}/view")->assertOk()->assertJsonPath('data.counted', false);
        $this->as($this->reader)->getJson("/api/v1/testimonies/{$t->id}")->assertOk(); // lecture : même dédoublonnage

        $this->guest()->postJson("/api/v1/testimonies/{$t->id}/view", ['device_id' => 'tel-A'])->assertJsonPath('data.counted', true);
        $this->guest()->postJson("/api/v1/testimonies/{$t->id}/view", ['device_id' => 'tel-A'])->assertJsonPath('data.counted', false);
        $this->guest()->withHeader('X-Device-Id', 'tel-B')->postJson("/api/v1/testimonies/{$t->id}/view")->assertJsonPath('data.counted', true);
        $this->assertSame(3, $t->fresh()->views_count);

        // Après 30 minutes, la même personne compte de nouveau.
        Carbon::setTestNow(now()->addMinutes(31));
        Cache::flush();
        $this->as($this->reader)->postJson("/api/v1/testimonies/{$t->id}/view", ['watched_seconds' => 45])->assertJsonPath('data.counted', true);
        $this->assertSame(4, $t->fresh()->views_count);
        $this->assertDatabaseHas('testimony_events', ['testimony_id' => $t->id, 'type' => 'watch', 'seconds' => 45]);

        // Non publié : jamais compté.
        $pending = $this->testimony('pending');
        $this->as($this->author)->postJson("/api/v1/testimonies/{$pending->id}/view")->assertJsonPath('data.counted', false);
        $this->guest()->postJson("/api/v1/testimonies/{$pending->id}/view")->assertNotFound();
    }

    public function test_daily_aggregation_counts_views_viewers_reactions_comments_shares(): void
    {
        $t = $this->testimony('approved');
        // Hier, 10 h à Porto-Novo.
        $yesterday = Carbon::now('Africa/Porto-Novo')->subDay()->setTime(10, 0);
        Carbon::setTestNow($yesterday->copy()->utc());

        $this->as($this->reader)->postJson("/api/v1/testimonies/{$t->id}/view", ['watched_seconds' => 30]);
        $this->guest()->postJson("/api/v1/testimonies/{$t->id}/view", ['device_id' => 'tel-A']);
        Carbon::setTestNow($yesterday->copy()->addHour()->utc());
        Cache::flush();
        $this->as($this->reader)->postJson("/api/v1/testimonies/{$t->id}/view"); // 2e vue, même personne
        $this->guest()->postJson("/api/v1/testimonies/{$t->id}/share")->assertOk();
        Reaction::create(['user_id' => $this->reader->id, 'testimony_id' => $t->id, 'type' => 'like']);
        Comment::create(['user_id' => $this->reader->id, 'testimony_id' => $t->id, 'body' => 'Amen !']);

        Carbon::setTestNow();
        $this->artisan('testimonies:aggregate-stats')->assertSuccessful();
        $this->artisan('testimonies:aggregate-stats')->assertSuccessful(); // relance : pas de doublon

        $row = TestimonyDailyStat::where('testimony_id', $t->id)->sole();
        $this->assertSame($yesterday->toDateString(), $row->date->toDateString());
        $this->assertSame(3, $row->views);
        $this->assertSame(2, $row->unique_viewers);
        $this->assertSame(1, $row->reactions);
        $this->assertSame(1, $row->comments);
        $this->assertSame(1, $row->shares);
        $this->assertSame(30, $row->video_watch_seconds);

        // Back-office : statistiques du témoignage et tableau de bord.
        $this->as($this->modo)->get("/moderation/{$t->id}/statistiques?jours=7")->assertOk()->assertSee('Audience');
        $this->as($this->modo)->get('/moderation/tableau-de-bord?jours=30')->assertOk()->assertSee($t->title);

        // Mis à la corbeille : absent des plus vus, toujours dans l'onglet Corbeille.
        $t->delete();
        $this->as($this->modo)->get('/moderation/tableau-de-bord?jours=30')->assertOk()->assertDontSee($t->title);
        $this->as($this->modo)->get('/moderation?onglet=trash')->assertOk()->assertSee($t->title);
    }

    // ─── Back-office et permissions ──────────────────────────────────────────

    public function test_back_office_tabs_bulk_actions_and_permissions(): void
    {
        $a = $this->testimony('pending');
        $b = $this->testimony('pending');
        $published = $this->testimony('approved');

        $this->as($this->reader)->get('/moderation')->assertForbidden();
        $this->as($this->reader)->postJson("/api/v1/moderation/{$a->id}/approve")->assertForbidden();

        $this->as($this->modo)->get('/moderation')->assertOk()->assertSee($a->title)->assertDontSee($published->title);
        foreach (['approved', 'rejected', 'featured', 'trash'] as $tab) {
            $this->as($this->modo)->get("/moderation?onglet={$tab}")->assertOk();
        }
        $this->as($this->modo)->get("/moderation?onglet=approved&q=" . urlencode($published->title))->assertOk()->assertSee($published->title);
        $this->as($this->modo)->get("/moderation/{$published->id}")->assertOk()->assertSee('Historique');

        $this->as($this->modo)->post('/moderation/actions-groupees', ['ids' => [$a->id, $b->id], 'action' => 'approve'])
            ->assertSessionHas('success');
        $this->assertSame('approved', $a->fresh()->status->value);
        $this->assertSame('approved', $b->fresh()->status->value);

        $this->as($this->modo)->post('/moderation/actions-groupees', ['ids' => [$a->id], 'action' => 'reject'])->assertSessionHasErrors('reason');
        $this->as($this->modo)->post('/moderation/actions-groupees', ['ids' => [$a->id], 'action' => 'reject', 'reason' => 'spam', 'rejection_reason' => 'Lien publicitaire'])
            ->assertSessionHas('success');
        $this->assertSame('Spam — Lien publicitaire', $a->fresh()->rejection_reason);

        $this->as($this->modo)->post('/moderation/actions-groupees', ['ids' => [$b->id], 'action' => 'trash'])->assertSessionHas('success');
        $this->assertSoftDeleted($b);
        $this->assertSame(4, AdminAuditLog::whereIn('action', ['testimony.approved', 'testimony.rejected', 'testimony.deleted'])->count());
    }

    // ─── Super-administrateur ────────────────────────────────────────────────

    public function test_super_admin_status_is_granted_only_by_command_and_audited(): void
    {
        $this->artisan('users:super-admin', ['email' => 'modo@example.com'])->assertFailed(); // pas administrateur
        $this->assertFalse((bool) $this->modo->fresh()->is_super_admin);

        $this->artisan('users:super-admin', ['email' => 'admin@example.com'])->assertSuccessful();
        $this->assertTrue($this->admin->fresh()->isSuperAdmin());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.super_admin_granted', 'subject_id' => $this->admin->id]);

        // Jamais modifiable par l'API.
        $this->as($this->reader)->putJson('/api/v1/users/me', ['display_name' => 'Lecteur', 'is_super_admin' => true])->assertOk();
        $this->assertFalse((bool) $this->reader->fresh()->is_super_admin);
        $this->as($this->admin)->putJson("/api/v1/admin/users/{$this->modo->id}/role", ['role' => 'administrateur', 'is_super_admin' => true]);
        $this->assertFalse((bool) $this->modo->fresh()->is_super_admin);

        $this->artisan('users:super-admin', ['email' => 'admin@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($this->admin->fresh()->isSuperAdmin());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.super_admin_revoked', 'subject_id' => $this->admin->id]);
    }
}

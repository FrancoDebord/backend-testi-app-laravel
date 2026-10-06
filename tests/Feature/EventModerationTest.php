<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\AppNotification;
use App\Models\Event;
use App\Models\EventReview;
use App\Models\User;
use App\Services\EventModeration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Événements : report, annulation motivée, signalements et masquage automatique, sondage
 * d'après l'événement, suivi des événements non confirmés, droits. docs/fonctionnalites/evenements.md
 */
class EventModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $org;
    private User $moderator;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = $this->makeUser('org', [
            'account_type' => 'organization', 'organization_name' => 'Église de la Grâce',
            'organization_type' => 'church', 'verification_status' => 'verified',
        ]);
        $this->moderator = $this->makeUser('modo', ['role' => 'moderateur']);
        $this->user = $this->makeUser('fidele');
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function event(array $attrs = []): Event
    {
        return Event::create(array_merge([
            'organizer_id' => $this->org->id, 'title' => 'Grande croisade', 'type' => 'crusade',
            'status' => 'published', 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(12),
        ], $attrs));
    }

    private function pastEvent(array $attrs = []): Event
    {
        return $this->event(array_merge(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)], $attrs));
    }

    private function going(Event $event, User $user): void
    {
        $this->actingAs($user)->postJson("/api/v1/events/{$event->id}/participation", ['status' => 'going'])->assertOk();
    }

    private function guest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    // ─── Report ──────────────────────────────────────────────────────────────

    public function test_postpone_with_new_date_keeps_original_and_notifies_participants(): void
    {
        $event = $this->event();
        $originalStart = $event->starts_at->copy();
        $this->going($event, $this->user);

        $res = $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/postpone", [
            'starts_at' => now()->addDays(30)->toIso8601String(),
            'ends_at'   => now()->addDays(31)->toIso8601String(),
            'reason'    => 'Travaux au stade',
        ])->assertOk();

        $res->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.isPostponed', true)
            ->assertJsonPath('data.dateTbd', false)
            ->assertJsonPath('data.phase', 'upcoming')
            ->assertJsonPath('data.statusReason', 'Travaux au stade')
            ->assertJsonPath('data.acceptsParticipation', true);
        $this->assertSame($originalStart->toIso8601String(), $event->fresh()->original_starts_at->toIso8601String());

        $notif = AppNotification::where('recipient_id', $this->user->id)->where('type', 'event_postponed')->first();
        $this->assertNotNull($notif);
        $this->assertSame($event->id, $notif->payload['event_id']);
        $this->assertStringContainsString('Travaux au stade', $notif->message);
        // L'auteur de l'action n'est pas notifié.
        $this->assertSame(0, AppNotification::where('recipient_id', $this->org->id)->count());
    }

    public function test_postpone_date_tbd_stays_visible_and_keeps_participation(): void
    {
        $event = $this->event(['starts_at' => now()->subDays(5), 'ends_at' => now()->subDays(4)->addHours(1)]);
        // Événement déjà « passé » : report refusé.
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/postpone", ['date_tbd' => true])->assertStatus(422);

        $event = $this->event();
        $this->going($event, $this->user);
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/postpone", ['date_tbd' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'postponed')
            ->assertJsonPath('data.dateTbd', true)
            ->assertJsonPath('data.phaseLabel', 'Reporté — date à confirmer');

        // Les dates passent : l'événement reste « à venir » (date à confirmer).
        $event->update(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)]);
        $ids = collect($this->guest()->getJson('/api/v1/events')->assertOk()->json('data'))->pluck('id');
        $this->assertContains($event->id, $ids);
        $this->assertNotContains($event->id, collect($this->getJson('/api/v1/events?scope=past')->json('data'))->pluck('id'));
        $this->actingAs($this->user)->getJson("/api/v1/events/{$event->id}")
            ->assertJsonPath('data.myParticipation', 'going')
            ->assertJsonPath('data.acceptsParticipation', true);

        // Nouvelle date fixée depuis le formulaire : redevient publié.
        $this->actingAs($this->org)->putJson("/api/v1/events/{$event->id}", [
            'status'    => 'postponed',
            'starts_at' => now()->addDays(40)->toIso8601String(),
            'ends_at'   => now()->addDays(41)->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.dateTbd', false);
        $this->assertSame(2, AppNotification::where('recipient_id', $this->user->id)->where('type', 'event_postponed')->count());
    }

    public function test_postpone_and_cancel_rights_and_validation(): void
    {
        $event = $this->event();
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/postpone", ['date_tbd' => true])->assertForbidden();
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/cancel")->assertForbidden();
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/postpone", [])->assertStatus(422)->assertJsonValidationErrors('starts_at');
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/postpone", [
            'starts_at' => now()->subDay()->toIso8601String(), 'ends_at' => now()->toIso8601String(),
        ])->assertStatus(422)->assertJsonValidationErrors('starts_at');
    }

    public function test_cancel_with_reason_notifies_participants(): void
    {
        $event = $this->event();
        $this->going($event, $this->user);

        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/cancel", ['reason' => 'Intempéries'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.statusReason', 'Intempéries');
        $this->assertSame(1, AppNotification::where('recipient_id', $this->user->id)->where('type', 'event_cancelled')->count());
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/cancel")->assertStatus(422);
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/postpone", ['date_tbd' => true])->assertStatus(422);

        // Annulation depuis le formulaire : participants prévenus aussi.
        $other = $this->event();
        $this->going($other, $this->user);
        $this->actingAs($this->org)->putJson("/api/v1/events/{$other->id}", ['status' => 'cancelled'])->assertOk();
        $this->assertSame(2, AppNotification::where('recipient_id', $this->user->id)->where('type', 'event_cancelled')->count());
    }

    // ─── Signalement ─────────────────────────────────────────────────────────

    public function test_report_once_per_person_and_not_by_organizer(): void
    {
        $event = $this->event();
        $this->guest()->postJson("/api/v1/events/{$event->id}/report", ['reason' => 'fake'])->assertUnauthorized();
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/report", ['reason' => 'fake'])->assertStatus(422);
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/report", ['reason' => 'nope'])->assertStatus(422);

        $this->actingAs($this->user)->getJson("/api/v1/events/{$event->id}")->assertJsonPath('data.canReport', true);
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/report", ['reason' => 'scam', 'comment' => 'Demande de dons'])->assertCreated();
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/report", ['reason' => 'fake'])->assertStatus(409);
        $this->actingAs($this->user)->getJson("/api/v1/events/{$event->id}")
            ->assertJsonPath('data.canReport', false)->assertJsonPath('data.hasReported', true);
        $this->assertSame(1, $event->fresh()->report_count);
    }

    public function test_three_reports_hide_event_until_moderation(): void
    {
        $event = $this->event();
        foreach (['a', 'b', 'c'] as $n) {
            $this->actingAs($this->makeUser("rep{$n}"))
                ->postJson("/api/v1/events/{$event->id}/report", ['reason' => 'not_christian'])->assertCreated();
        }
        $event->refresh();
        $this->assertTrue($event->isHidden());
        $this->assertNull($event->hidden_by);

        // Masqué : absent des listes, 404 pour le public, visible des gestionnaires et modérateurs.
        $this->assertNotContains($event->id, collect($this->guest()->getJson('/api/v1/events')->json('data'))->pluck('id'));
        $this->guest()->getJson("/api/v1/events/{$event->id}")->assertNotFound();
        $this->actingAs($this->user)->getJson("/api/v1/events/{$event->id}")->assertNotFound();
        $this->actingAs($this->org)->getJson("/api/v1/events/{$event->id}")->assertOk()->assertJsonPath('data.isHidden', true);

        // File de modération
        $this->actingAs($this->user)->getJson('/api/v1/events/moderation')->assertForbidden();
        $queue = $this->actingAs($this->moderator)->getJson('/api/v1/events/moderation')->assertOk();
        $queue->assertJsonPath('data.0.event.id', $event->id)->assertJsonPath('data.0.pendingReports', 3);
        $this->assertSame(1, $queue->json('data.0.reliability.reported'));

        // Rejeter les signalements : rétabli (masquage automatique).
        $this->actingAs($this->moderator)->postJson("/api/v1/events/{$event->id}/dismiss")->assertOk()->assertJsonPath('data.isHidden', false);
        $this->assertSame(0, $event->reports()->whereNull('reviewed_at')->count());
        $this->actingAs($this->moderator)->getJson('/api/v1/events/moderation')->assertJsonCount(0, 'data');

        // Masquer puis rétablir à la main.
        $this->actingAs($this->moderator)->postJson("/api/v1/events/{$event->id}/hide", ['reason' => 'Faux événement'])
            ->assertOk()->assertJsonPath('data.isHidden', true);
        $this->actingAs($this->moderator)->getJson('/api/v1/events/moderation?filter=hidden')->assertJsonPath('data.0.event.id', $event->id);
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/restore")->assertForbidden();
        $this->actingAs($this->moderator)->postJson("/api/v1/events/{$event->id}/restore")->assertOk()->assertJsonPath('data.isHidden', false);
    }

    // ─── Sondage d'après l'événement ─────────────────────────────────────────

    public function test_survey_rules_and_results(): void
    {
        $upcoming = $this->event();
        $this->actingAs($this->user)->postJson("/api/v1/events/{$upcoming->id}/review", ['attended' => 'yes', 'rating' => 5])->assertStatus(422);

        $event = $this->pastEvent();
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/review", ['attended' => 'yes', 'rating' => 5])->assertForbidden();
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/review", ['attended' => 'yes'])
            ->assertStatus(422)->assertJsonValidationErrors('rating');

        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/review", ['attended' => 'yes', 'rating' => 4, 'body' => 'Belle soirée'])
            ->assertOk()
            ->assertJsonPath('data.survey.yes', 1)
            ->assertJsonPath('data.survey.confirmedPercent', 100)
            ->assertJsonPath('data.survey.mine.rating', 4);
        // Modifier sa réponse : une seule par personne ; la note est ignorée hors « Oui ».
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/review", ['attended' => 'absent', 'rating' => 5])
            ->assertOk()->assertJsonPath('data.survey.yes', 0)->assertJsonPath('data.survey.absent', 1)
            ->assertJsonPath('data.survey.ratingAverage', null);
        $this->assertSame(1, EventReview::count());

        // Plus modifiable après 30 jours.
        EventReview::query()->update(['created_at' => now()->subDays(31)]);
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/review", ['attended' => 'yes', 'rating' => 5])->assertStatus(422);

        $this->guest()->getJson("/api/v1/events/{$event->id}/reviews")->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_majority_of_no_marks_event_unconfirmed_and_queues_it(): void
    {
        $event = $this->pastEvent();
        $votes = ['no', 'no', 'yes', 'absent'];
        foreach ($votes as $i => $vote) {
            $this->actingAs($this->makeUser("v{$i}"))->postJson("/api/v1/events/{$event->id}/review",
                ['attended' => $vote, 'rating' => $vote === 'yes' ? 3 : null])->assertOk();
        }
        $event->refresh();
        $this->assertTrue($event->isUnconfirmed());
        $this->assertSame(33, $event->confirmedPercent());

        $this->actingAs($this->user)->getJson("/api/v1/events/{$event->id}")->assertJsonPath('data.isUnconfirmed', true);
        $this->actingAs($this->moderator)->getJson('/api/v1/events/moderation?filter=unconfirmed')
            ->assertJsonPath('data.0.event.id', $event->id)
            ->assertJsonPath('data.0.reliability.unconfirmed', 1);
        $this->actingAs($this->moderator)->getJson("/api/v1/events/{$event->id}")
            ->assertJsonPath('data.moderation.reliability.unconfirmed', 1);

        $this->actingAs($this->moderator)->postJson("/api/v1/events/{$event->id}/dismiss")->assertOk();
        $this->actingAs($this->moderator)->getJson('/api/v1/events/moderation?filter=unconfirmed')->assertJsonCount(0, 'data');
    }

    public function test_reliable_organizer_badge(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->pastEvent(['survey_yes_count' => 3, 'survey_no_count' => 0]);
        }
        $event = $this->event();
        $this->guest()->getJson("/api/v1/events/{$event->id}")->assertJsonPath('data.organizer.isReliable', true);
        $this->pastEvent(['unconfirmed_at' => now()]);
        $this->guest()->getJson("/api/v1/events/{$event->id}")->assertJsonPath('data.organizer.isReliable', false);
    }

    public function test_survey_invitations_are_sent_once(): void
    {
        $event = $this->event();
        $this->going($event, $this->user);
        $commenter = $this->makeUser('commentateur');
        $this->actingAs($commenter)->postJson("/api/v1/events/{$event->id}/comments", ['body' => 'Quelle grâce !'])->assertCreated();
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/comments", ['body' => 'Merci à tous'])->assertCreated();
        $event->update(['starts_at' => now()->subDays(2), 'ends_at' => now()->subHour()]);
        $old = $this->pastEvent(['starts_at' => now()->subDays(30), 'ends_at' => now()->subDays(29)]);

        $this->artisan('events:send-surveys')->assertSuccessful();
        $this->assertNotNull($event->fresh()->survey_sent_at);
        $this->assertNull($old->fresh()->survey_sent_at);
        $this->assertSame(1, AppNotification::where('recipient_id', $this->user->id)->where('type', 'event_survey')->count());
        $this->assertSame(1, AppNotification::where('recipient_id', $commenter->id)->where('type', 'event_survey')->count());
        $this->assertSame(0, AppNotification::where('recipient_id', $this->org->id)->where('type', 'event_survey')->count());

        app(EventModeration::class)->sendSurveys();
        $this->assertSame(2, AppNotification::where('type', 'event_survey')->count());
    }

    // ─── Site ────────────────────────────────────────────────────────────────

    public function test_web_compact_layout_is_remembered(): void
    {
        $event = $this->event(['title' => 'Camp de jeunes de Parakou']);
        $this->get('/evenements')->assertOk()->assertSee('Camp de jeunes de Parakou')->assertSee('Liste compacte');

        $this->post('/evenements/affichage', ['layout' => 'compact'])->assertRedirect()->assertCookie('event_layout', 'compact');
        $this->withCookie('event_layout', 'compact')->get('/evenements')->assertOk()
            ->assertSee('Camp de jeunes de Parakou')->assertSee('aria-pressed="true"', false);
        $this->post('/evenements/affichage', ['layout' => 'nope'])->assertSessionHasErrors('layout');
    }

    public function test_web_pages_postpone_report_survey_and_moderation(): void
    {
        $event = $this->event();
        $this->going($event, $this->user);
        $this->actingAs($this->org)->get("/evenements/{$event->id}")->assertOk()->assertSee('Reporter')->assertSee("Annuler l'événement", false);
        $this->actingAs($this->org)->post("/evenements/{$event->id}/reporter", ['date_tbd' => '1', 'reason' => 'Pluies'])
            ->assertRedirect("/evenements/{$event->id}");
        $this->actingAs($this->user)->get("/evenements/{$event->id}")->assertOk()
            ->assertSee('date à confirmer')->assertSee('Signaler cet événement');

        $this->actingAs($this->user)->post("/evenements/{$event->id}/signaler", ['reason' => 'fake', 'comment' => 'Introuvable'])->assertRedirect();
        $this->assertSame(1, $event->fresh()->report_count);

        $past = $this->pastEvent(['title' => 'Concert passé']);
        $this->actingAs($this->user)->get("/evenements/{$past->id}")->assertOk()->assertSee('Votre avis')->assertSee("L'événement a-t-il eu lieu ?", false);
        $this->actingAs($this->user)->post("/evenements/{$past->id}/avis", ['attended' => 'yes', 'rating' => 5, 'body' => 'Béni !'])
            ->assertRedirect();
        $this->actingAs($this->user)->get("/evenements/{$past->id}")->assertOk()->assertSee('100 %')->assertSee('Béni !');

        $this->actingAs($this->user)->get('/evenements/moderation')->assertForbidden();
        $this->actingAs($this->moderator)->get('/evenements/moderation')->assertOk()->assertSee('Introuvable');
        $this->actingAs($this->moderator)->get('/evenements/moderation?filtre=masques')->assertOk();
        $this->actingAs($this->moderator)->get('/evenements/moderation?filtre=non-confirmes')->assertOk();
        $this->actingAs($this->moderator)->post("/evenements/moderation/{$event->id}/masquer", ['reason' => 'Doublon'])->assertRedirect();
        $this->assertTrue($event->fresh()->isHidden());
        $this->actingAs($this->user)->get("/evenements/{$event->id}")->assertNotFound();
        $this->actingAs($this->moderator)->get("/evenements/{$event->id}")->assertOk()->assertSee('masqué par la modération');
        $this->actingAs($this->moderator)->get("/profiles/{$this->org->id}")->assertOk()->assertSee('Événements organisés (modération)');
    }

    // ─── Mentions dans les commentaires (docs/fonctionnalites/mentions.md) ──

    public function test_mention_in_event_comment_is_notified_and_rendered(): void
    {
        $event  = $this->event();
        $friend = $this->makeUser('ami', ['display_name' => 'Paul Mbeki']);

        $res = $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/comments", [
            'body' => "Merci @[Paul](user:{$friend->id}) pour la louange !",
        ])->assertCreated();
        // Texte lisible avec le nom réel, mentions séparées.
        $res->assertJsonPath('data.body', 'Merci @Paul Mbeki pour la louange !')
            ->assertJsonPath('data.mentions.0.id', strtolower($friend->id));

        $notif = AppNotification::where('recipient_id', $friend->id)->where('type', 'mention')->first();
        $this->assertNotNull($notif);
        $this->assertSame($event->id, $notif->payload['event_id']);
        $this->assertSame(0, AppNotification::where('recipient_id', $this->user->id)->where('type', 'mention')->count());

        $this->getJson("/api/v1/events/{$event->id}/comments")->assertJsonPath('data.0.body', 'Merci @Paul Mbeki pour la louange !');
        $this->get("/evenements/{$event->id}?section=commentaires")->assertOk()->assertSee("/profiles/{$friend->id}", false);

        // Témoignage officiel : texte lisible, sans jeton.
        $commentId = $res->json('data.id');
        $this->actingAs($this->org)->postJson("/api/v1/events/{$event->id}/comments/{$commentId}/promote")->assertCreated();
        $this->assertSame('Merci @Paul Mbeki pour la louange !', \App\Models\Testimony::where('event_id', $event->id)->value('body_text'));
    }

    public function test_draft_cannot_be_reported(): void
    {
        $event = $this->event(['status' => EventStatus::Draft->value]);
        $this->actingAs($this->user)->postJson("/api/v1/events/{$event->id}/report", ['reason' => 'fake'])->assertNotFound();
    }
}

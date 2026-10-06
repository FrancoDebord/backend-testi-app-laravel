<?php

namespace App\Services;

use App\Jobs\SyncProphecyReminderJob;
use App\Models\DeviceToken;
use App\Models\Prophecy;
use App\Models\ProphecyPrayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Paroles prophétiques du carnet privé : règles communes à l'API mobile et au site web.
 * Une parole n'est visible que de son auteur. Voir docs/fonctionnalites/paroles-prophetiques.md
 */
class Prophecies
{
    /** Champs dont dépend le rappel programmé sur le téléphone (réglage, état, texte de la notification). */
    private const REMINDER_FIELDS = ['status', 'reminder_frequency', 'reminder_time', 'reminder_weekday', 'title', 'body_text', 'received_on'];

    public const MESSAGES = [
        'body_text.required_without'   => 'Écrivez la parole ou enregistrez-la en audio.',
        'reminder_time.required_with'  => "Choisissez l'heure du rappel.",
        'reminder_time.regex'          => 'Heure du rappel au format HH:MM.',
        'reminder_weekday.required_if' => 'Choisissez le jour du rappel.',
        'due_on.after_or_equal'        => "L'échéance ne peut pas précéder la date de la parole.",
    ];

    /** Mes paroles : en attente d'abord, puis les plus récentes (`$status` = waiting | fulfilled | null). */
    public function list(User $user, ?string $status = null): Builder
    {
        return $user->prophecies()->getQuery()
            ->when(in_array($status, [Prophecy::WAITING, Prophecy::FULFILLED], true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'waiting' THEN 0 ELSE 1 END")
            ->orderByDesc('received_on')->orderByDesc('created_at');
    }

    /** Nombre de paroles par état : ['waiting' => n, 'fulfilled' => n]. */
    public function counts(User $user): array
    {
        return [
            Prophecy::WAITING   => $user->prophecies()->where('status', Prophecy::WAITING)->count(),
            Prophecy::FULFILLED => $user->prophecies()->where('status', Prophecy::FULFILLED)->count(),
        ];
    }

    /** Parole de l'utilisateur ; 404 pour toute autre personne (son existence n'est pas révélée). */
    public function find(User $user, ?string $id): Prophecy
    {
        $prophecy = filled($id) ? $user->prophecies()->find($id) : null;
        abort_if(!$prophecy, 404, 'Parole introuvable.');

        return $prophecy;
    }

    public function rules(bool $partial = false): array
    {
        return [
            'title'              => ['nullable', 'string', 'max:150'],
            'received_on'        => ['nullable', 'date'],
            'body_text'          => [$partial ? 'nullable' : 'required_without:audio_url', 'nullable', 'string', 'max:20000'],
            'audio_url'          => ['nullable', 'string', 'max:500'],
            'audio_duration'     => ['nullable', 'integer', 'min:0'],
            'given_by'           => ['nullable', 'string', 'max:150'],
            'due_on'             => ['nullable', 'date', ...($partial ? [] : ['after_or_equal:received_on'])],
            'reminder_frequency' => ['nullable', Rule::in(['daily', 'weekly'])],
            'reminder_time'      => ['nullable', 'required_with:reminder_frequency', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'reminder_weekday'   => ['nullable', 'required_if:reminder_frequency,weekly', 'integer', 'between:1,7'],
        ];
    }

    public function updateRules(): array
    {
        return [
            ...$this->rules(partial: true),
            'status'       => ['sometimes', Rule::in([Prophecy::WAITING, Prophecy::FULFILLED])],
            'fulfilled_on' => ['nullable', 'date'],
        ];
    }

    public function create(User $user, array $data): Prophecy
    {
        $data['received_on'] ??= now()->toDateString();

        $prophecy = $user->prophecies()->create($this->normalize($data))->refresh();
        $this->syncDevices($prophecy);

        return $prophecy;
    }

    /**
     * Modification partielle ; `status` permet aussi de marquer « accomplie » sans témoignage.
     *
     * @throws ValidationException si la parole doit rester accomplie (témoignage déjà publié)
     */
    public function update(Prophecy $prophecy, array $data): Prophecy
    {
        $data = $this->normalize($data, $prophecy);
        if (($data['status'] ?? null) === Prophecy::FULFILLED && !$prophecy->isFulfilled()) {
            $data['fulfilled_on'] ??= now()->toDateString();
        }
        if (($data['status'] ?? null) === Prophecy::WAITING) {
            if ($prophecy->testimony_id) {
                throw ValidationException::withMessages(['status' => 'Un témoignage est déjà publié pour cette parole : elle reste accomplie.']);
            }
            $data['fulfilled_on'] = null;
        }
        // Échéance : jamais avant la date de la parole (vérifiée ici, les champs pouvant être partiels).
        $receivedOn = $data['received_on'] ?? $prophecy->received_on?->toDateString();
        $dueOn      = array_key_exists('due_on', $data) ? $data['due_on'] : $prophecy->due_on?->toDateString();
        if ($receivedOn && $dueOn && strtotime($dueOn) < strtotime($receivedOn)) {
            throw ValidationException::withMessages(['due_on' => self::MESSAGES['due_on.after_or_equal']]);
        }
        $prophecy->update($data);
        if ($prophecy->wasChanged(self::REMINDER_FIELDS)) {
            $this->syncDevices($prophecy);
        }

        return $prophecy->refresh();
    }

    /** Suppression douce ; le rappel est annulé sur les téléphones. */
    public function delete(Prophecy $prophecy): void
    {
        $prophecy->delete();
        $this->syncDevices($prophecy);
    }

    /** « J'ai prié » (note facultative). */
    public function pray(Prophecy $prophecy, ?string $note = null, ?string $prayedAt = null): ProphecyPrayer
    {
        $prayer = $prophecy->prayers()->create([
            'note'      => filled($note) ? trim($note) : null,
            'prayed_at' => $prayedAt ?? now(),
        ]);
        $this->recount($prophecy);

        return $prayer;
    }

    public function deletePrayer(Prophecy $prophecy, int $prayerId): void
    {
        $prophecy->prayers()->whereKey($prayerId)->delete();
        $this->recount($prophecy);
    }

    /** Témoignage de l'accomplissement : la parole passe accomplie et peut être montrée avec lui. */
    public function attachTestimony(Prophecy $prophecy, string $testimonyId, bool $public): void
    {
        $prophecy->update([
            'status'       => Prophecy::FULFILLED,
            'fulfilled_on' => $prophecy->fulfilled_on ?? now()->toDateString(),
            'testimony_id' => $testimonyId,
            'is_public'    => $public,
        ]);
        if ($prophecy->wasChanged('status')) {
            $this->syncDevices($prophecy);
        }
    }

    /**
     * Message silencieux aux téléphones de l'auteur : ils reprogramment le rappel tout de suite
     * (sinon, à la prochaine ouverture des paroles). Rien sans FCM ni appareil enregistré ;
     * un échec n'empêche jamais l'enregistrement.
     */
    private function syncDevices(Prophecy $prophecy): void
    {
        try {
            if (!app(FcmClient::class)->isConfigured()
                || !DeviceToken::where('user_id', $prophecy->user_id)->whereIn('platform', ['android', 'ios'])->exists()) {
                return;
            }
            SyncProphecyReminderJob::dispatch($prophecy->user_id, $prophecy->id);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Champs vides → null ; pas de rappel → heure et jour effacés. */
    private function normalize(array $data, ?Prophecy $current = null): array
    {
        foreach (['title', 'body_text', 'given_by', 'audio_url'] as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = filled($data[$f]) ? trim($data[$f]) : null;
            }
        }
        if (array_key_exists('reminder_frequency', $data) && $data['reminder_frequency'] === null) {
            $data['reminder_time'] = null;
            $data['reminder_weekday'] = null;
        }
        if (($data['reminder_frequency'] ?? $current?->reminder_frequency) === 'daily') {
            $data['reminder_weekday'] = null;
        }

        return $data;
    }

    private function recount(Prophecy $prophecy): void
    {
        $prophecy->update([
            'prayer_count'   => $prophecy->prayers()->count(),
            'last_prayed_at' => $prophecy->prayers()->max('prayed_at'),
        ]);
    }
}

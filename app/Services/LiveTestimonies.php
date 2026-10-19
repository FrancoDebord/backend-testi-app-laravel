<?php

namespace App\Services;

use App\Enums\LiveStatus;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Témoigner après un direct terminé (docs/fonctionnalites/lives.md, « Directs passés ») : règles du champ
 * live_id, partagées par POST /testimonies, POST /videos/upload-init et le formulaire de publication du site.
 */
class LiveTestimonies
{
    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return ['live_id' => ['nullable', 'string', 'uuid']];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'live_id.string' => __('lives_replay.validation.live_invalid'),
            'live_id.uuid'   => __('lives_replay.validation.live_invalid'),
        ];
    }

    /**
     * Direct auquel rattacher le témoignage, ou null si aucun n'est demandé.
     * Il doit exister, être visible de la personne et être terminé.
     *
     * @throws ContentActionException 422, erreurs { live_id: [message] }
     */
    public function resolve(?string $liveId, ?User $user): ?LiveSession
    {
        if (blank($liveId)) {
            return null;
        }
        if (!Str::isUuid($liveId)) {
            throw self::invalid(__('lives_replay.validation.live_invalid'));
        }

        $live = LiveSession::find($liveId);
        if (!$live || !$live->isVisibleTo($user)) {
            throw self::invalid(__('lives_replay.validation.live_not_found'));
        }
        if ($live->status !== LiveStatus::Ended) {
            throw self::invalid(__('lives_replay.validation.live_not_ended'));
        }

        return $live;
    }

    private static function invalid(string $message): ContentActionException
    {
        return new ContentActionException($message, 422, ['live_id' => [$message]]);
    }
}

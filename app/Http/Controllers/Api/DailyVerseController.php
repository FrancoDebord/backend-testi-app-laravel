<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyVerse;
use App\Models\DailyVerseReaction;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyVerseController extends Controller
{
    use ApiResponse;

    // GET /daily-verse  (public)
    public function today(Request $request): JsonResponse
    {
        $verse = DailyVerse::today();

        if (!$verse) {
            return $this->error('Aucun verset programmé.', 404);
        }

        $userId    = $request->user()?->id;
        $myReactions = $userId ? $verse->userReactions($userId) : [];

        return $this->success([
            'id'           => $verse->id,
            'verseText'    => $verse->verse_text,
            'reference'    => $verse->reference,
            'theme'        => $verse->theme,
            'themeLabel'   => $verse->themeLabel(),
            'weekNumber'   => $verse->week_number,
            'date'         => $verse->scheduled_date?->toDateString(),
            'counts'       => [
                'likes'   => $verse->like_count,
                'prayers' => $verse->prayer_count,
                'amens'   => $verse->amen_count,
                'shares'  => $verse->share_count,
            ],
            'myReactions'  => $myReactions,
        ]);
    }

    // POST /daily-verse/react  { type: "like"|"pray"|"amen" }  (authentifié)
    public function react(Request $request): JsonResponse
    {
        $request->validate(['type' => 'required|in:like,pray,amen']);

        $verse = DailyVerse::today();
        if (!$verse) return $this->notFound();

        $type = $request->type;

        $exists = DailyVerseReaction::where('daily_verse_id', $verse->id)
                                    ->where('user_id', $request->user()->id)
                                    ->where('type', $type)
                                    ->exists();

        if ($exists) {
            return $this->error('Réaction déjà enregistrée.', 409);
        }

        DailyVerseReaction::create([
            'daily_verse_id' => $verse->id,
            'user_id'        => $request->user()->id,
            'type'           => $type,
        ]);

        $countField = match($type) {
            'like' => 'like_count',
            'pray' => 'prayer_count',
            'amen' => 'amen_count',
        };
        $verse->increment($countField);

        return $this->success(null, 'Réaction enregistrée');
    }

    // DELETE /daily-verse/react  { type: "like"|"pray"|"amen" }  (authentifié)
    public function unreact(Request $request): JsonResponse
    {
        $request->validate(['type' => 'required|in:like,pray,amen']);

        $verse = DailyVerse::today();
        if (!$verse) return $this->notFound();

        $type = $request->type;

        $deleted = DailyVerseReaction::where('daily_verse_id', $verse->id)
                                     ->where('user_id', $request->user()->id)
                                     ->where('type', $type)
                                     ->delete();

        if ($deleted) {
            $countField = match($type) {
                'like' => 'like_count',
                'pray' => 'prayer_count',
                'amen' => 'amen_count',
            };
            $verse->decrement($countField);
        }

        return $this->success(null, 'Réaction retirée');
    }

    // POST /daily-verse/share  (authentifié)
    public function share(): JsonResponse
    {
        $verse = DailyVerse::today();
        if (!$verse) return $this->notFound();

        $verse->increment('share_count');

        return $this->success(null, 'Partage enregistré');
    }
}

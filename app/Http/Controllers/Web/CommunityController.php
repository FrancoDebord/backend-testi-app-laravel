<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CommunityDirectory;
use App\Services\FollowException;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page « Communauté » (organisations et personnes à suivre) et bouton « Suivre ».
 * Voir docs/fonctionnalites/abonnements.md
 */
class CommunityController extends Controller
{
    public function __construct(
        private readonly CommunityDirectory $directory,
        private readonly FollowService $follows,
    ) {}

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('tab'), CommunityDirectory::TABS) ? $request->query('tab') : 'organizations';
        $q   = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        return view('community.index', [
            'tab'      => $tab,
            'q'        => $q,
            'accounts' => $this->directory->search($tab, $q, $request->user()),
        ]);
    }

    /** « Mes abonnements » : comptes que la personne connectée suit. */
    public function following(Request $request): View
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        return view('community.following', [
            'q'        => $q,
            'accounts' => $this->directory->following($request->user(), $q),
        ]);
    }

    /** « Mes abonnés » : comptes qui suivent la personne connectée (bouton « Suivre en retour »). */
    public function followers(Request $request): View
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        return view('community.followers', [
            'q'        => $q,
            'accounts' => $this->directory->followers($request->user(), $q),
        ]);
    }

    public function follow(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $target = User::findOrFail($id);

        try {
            $this->follows->follow($request->user(), $target);
        } catch (FollowException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], $e->status())
                : back()->with('error', $e->getMessage());
        }

        return $this->answer($request, $target, true, "Vous suivez {$target->display_name}.");
    }

    public function unfollow(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $target = User::findOrFail($id);
        $this->follows->unfollow($request->user(), $target);

        return $this->answer($request, $target, false, "Vous ne suivez plus {$target->display_name}.");
    }

    private function answer(Request $request, User $target, bool $following, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'following'     => $following,
                'followerCount' => $target->fresh()->follower_count,
                'message'       => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}

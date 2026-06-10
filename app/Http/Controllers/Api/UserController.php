<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonyResource;
use App\Http\Resources\UserResource;
use App\Models\AppNotification;
use App\Models\Follow;
use App\Models\Testimony;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function show(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->notFound();

        return $this->success(new UserResource($user));
    }

    public function updateMe(Request $request): JsonResponse
    {
        $request->validate([
            'display_name' => ['nullable', 'string', 'max:100'],
            'country'      => ['nullable', 'string'],
            'bio'          => ['nullable', 'string', 'max:500'],
            'avatar_url'   => ['nullable', 'string'],
        ]);

        $request->user()->update($request->only(['display_name', 'country', 'bio', 'avatar_url']));

        return $this->success(new UserResource($request->user()->fresh()), 'Profil mis à jour');
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'max:5120']]);

        $path = $request->file('avatar')->store('avatars', 'public');
        $url  = asset('storage/' . $path);

        $request->user()->update(['avatar_url' => $url]);

        return $this->success(['avatar_url' => $url], 'Avatar mis à jour');
    }

    public function testimonies(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->notFound();

        $query = Testimony::with('user')->where('user_id', $id);

        if ($request->user()?->id !== $id) {
            $query->published();
        }

        $testimonies = $query->latest()->paginate(20);

        return $this->paginated(
            TestimonyResource::collection($testimonies->items()),
            ['total' => $testimonies->total()]
        );
    }

    public function follow(Request $request, string $id): JsonResponse
    {
        if ($id === $request->user()->id) {
            return $this->error('Vous ne pouvez pas vous suivre vous-même');
        }

        $target = User::find($id);
        if (!$target) return $this->notFound();

        $exists = Follow::where('follower_id', $request->user()->id)
                        ->where('following_id', $id)
                        ->exists();

        if (!$exists) {
            Follow::create([
                'follower_id'  => $request->user()->id,
                'following_id' => $id,
                'created_at'   => now(),
            ]);

            $request->user()->increment('following_count');
            $target->increment('follower_count');

            // Notify followed user
            AppNotification::create([
                'recipient_id' => $id,
                'actor_id'     => $request->user()->id,
                'actor_name'   => $request->user()->display_name,
                'actor_avatar' => $request->user()->avatar_url,
                'type'         => 'follow',
                'message'      => $request->user()->display_name . ' vous suit maintenant',
                'created_at'   => now(),
            ]);
        }

        return $this->success(null, 'Abonnement effectué');
    }

    public function unfollow(Request $request, string $id): JsonResponse
    {
        $deleted = Follow::where('follower_id', $request->user()->id)
                         ->where('following_id', $id)
                         ->delete();

        if ($deleted) {
            $request->user()->decrement('following_count');
            User::where('id', $id)->decrement('follower_count');
        }

        return $this->success(null, 'Abonnement annulé');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'is_private_account'     => ['nullable', 'boolean'],
            'comment_permission'     => ['nullable', 'in:everyone,followers,nobody'],
            'push_comments'          => ['nullable', 'boolean'],
            'push_likes'             => ['nullable', 'boolean'],
            'push_prayers'           => ['nullable', 'boolean'],
            'push_approval'          => ['nullable', 'boolean'],
            'push_new_followed'      => ['nullable', 'boolean'],
            'app_theme'              => ['nullable', 'in:light,dark,system'],
            'language'               => ['nullable', 'string', 'max:5'],
            'last_bible_book'        => ['nullable', 'integer', 'min:1', 'max:66'],
            'last_bible_chapter'     => ['nullable', 'integer', 'min:1'],
            'last_bible_translation' => ['nullable', 'string', 'max:10'],
        ]);

        $settings = $request->user()->settings ?? $request->user()->settings()->create([]);
        $settings->update($request->only([
            'is_private_account', 'comment_permission', 'push_comments',
            'push_likes', 'push_prayers', 'push_approval', 'push_new_followed',
            'app_theme', 'language',
            'last_bible_book', 'last_bible_chapter', 'last_bible_translation',
        ]));

        return $this->success($settings, 'Paramètres mis à jour');
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        if (!password_verify($request->password, $request->user()->password)) {
            return $this->error('Mot de passe incorrect', 403);
        }

        $request->user()->tokens()->delete();
        $request->user()->delete();

        return $this->success(null, 'Compte supprimé');
    }
}

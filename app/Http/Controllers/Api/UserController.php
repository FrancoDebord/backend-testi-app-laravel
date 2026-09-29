<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonyResource;
use App\Http\Resources\UserResource;
use App\Models\AppNotification;
use App\Models\Follow;
use App\Models\Testimony;
use App\Models\User;
use App\Services\CommunityDirectory;
use App\Services\FollowException;
use App\Services\FollowService;
use App\Services\OrganizationAccounts;
use App\Services\ProfileCover;
use App\Support\PhoneNumber;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ApiResponse;

    public function show(Request $request, string $id): JsonResponse
    {
        $user = User::withFollowState($request->user('sanctum'))->withPublishedTestimonyCount()->find($id);
        if (!$user) return $this->notFound();

        return $this->success(new UserResource($user));
    }

    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        // Téléphone : avec « phone_country » (indicatif), le numéro national est vérifié puis mis au format
        // international, comme sur le site ; sans, ancien format (numéro libre). docs/fonctionnalites/telephone.md
        $withCountry = $request->filled('phone_country');
        $phoneRules  = $withCountry
            ? PhoneNumber::rules(false, $user)
            : ['phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->id)]];

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:100'],
            'country'      => ['nullable', 'string', 'max:100'],
            'bio'          => ['nullable', 'string', 'max:500'],
            'avatar_url'   => ['nullable', 'string'],
        ] + $phoneRules + OrganizationAccounts::updateRules($user), PhoneNumber::MESSAGES + [
            'phone.unique' => 'Ce numéro est déjà associé à un autre compte.',
        ]);

        // Un numéro modifié n'est plus vérifié par SMS : il ne sert plus à la connexion.
        // Numéro vérifié : il ne se change que par une nouvelle connexion par SMS.
        if ($request->has('phone') && !($user->hasVerifiedPhone() && $withCountry)) {
            $withCountry
                ? $user->setContactPhone(PhoneNumber::toE164($validated['phone_country'], $validated['phone'] ?? null), strtolower($validated['phone_country']))
                : $user->setContactPhone($request->input('phone') ?: null, null);
        }
        $user->update($request->only(['display_name', 'country', 'bio', 'avatar_url']));

        // Champs organisation : account_type, verification_status et verified_*
        // ne sont jamais modifiables ici (docs/fonctionnalites/comptes-organisation.md).
        OrganizationAccounts::updateProfile($user, $validated);

        return $this->success(UserResource::owner($request->user()->fresh()), 'Profil mis à jour');
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'max:5120']]);

        $path = $request->file('avatar')->store('avatars', 'public');
        $url  = asset('storage/' . $path);

        $request->user()->update(['avatar_url' => $url]);

        return $this->success(['avatar_url' => $url], 'Avatar mis à jour');
    }

    /** Photo de couverture du profil (multipart « cover »). Voir docs/fonctionnalites/photo-de-couverture.md */
    public function uploadCover(Request $request): JsonResponse
    {
        $request->validate(['cover' => ProfileCover::rules(required: true)], ProfileCover::MESSAGES);

        $url = ProfileCover::store($request->user(), $request->file('cover'));

        return $this->success(['cover_url' => $url], 'Photo de couverture mise à jour');
    }

    public function deleteCover(Request $request): JsonResponse
    {
        ProfileCover::remove($request->user());

        return $this->success(['cover_url' => null], 'Photo de couverture retirée');
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

    /**
     * Identifiants des comptes suivis par la personne connectée : l'application affiche ainsi le bon état
     * de tous ses boutons « Suivre » sans une requête par auteur. docs/fonctionnalites/abonnements.md
     */
    public function followingIds(Request $request): JsonResponse
    {
        return $this->success(
            DB::table('follows')->where('follower_id', $request->user()->id)->limit(5000)->pluck('following_id')
        );
    }

    /** « Mes abonnements » : GET /users/me/following?q=&page= (UserResource paginés, meta de pagination). */
    public function following(Request $request, CommunityDirectory $directory): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100']);
        $accounts = $directory->following($request->user(), $request->query('q'));

        return $this->paginated(UserResource::collection($accounts->items()), [
            'current_page' => $accounts->currentPage(),
            'last_page'    => $accounts->lastPage(),
            'total'        => $accounts->total(),
        ]);
    }

    /** Suivre un compte (jamais soi-même). Réponse : { following, followerCount }. */
    public function follow(Request $request, string $id, FollowService $follows): JsonResponse
    {
        $target = User::find($id);
        if (!$target) return $this->notFound();

        try {
            $follows->follow($request->user(), $target);
        } catch (FollowException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        return $this->success(['following' => true, 'followerCount' => $target->fresh()->follower_count], 'Abonnement effectué');
    }

    public function unfollow(Request $request, string $id, FollowService $follows): JsonResponse
    {
        $target = User::find($id);
        if (!$target) return $this->notFound();

        $follows->unfollow($request->user(), $target);

        return $this->success(['following' => false, 'followerCount' => $target->fresh()->follower_count], 'Abonnement annulé');
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

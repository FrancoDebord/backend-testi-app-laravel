<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TestimonyResource;
use App\Http\Resources\UserResource;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\ModerationLog;
use App\Models\Testimony;
use App\Models\User;
use App\Services\OrganizationAccounts;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    use ApiResponse;

    public function stats(): JsonResponse
    {
        $today = now()->toDateString();

        return $this->success([
            'totalUsers'           => User::count(),
            'newUsersToday'        => User::whereDate('created_at', $today)->count(),
            'totalTestimonies'     => Testimony::count(),
            'pendingTestimonies'   => Testimony::pending()->count(),
            'approvedTestimonies'  => Testimony::where('status', 'approved')->count(),
            'viewsThisMonth'       => Testimony::whereMonth('created_at', now()->month)->sum('views_count'),
            'commentsThisMonth'    => \App\Models\Comment::whereMonth('created_at', now()->month)->count(),
            'approvalRate'         => $this->approvalRate(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query();

        if ($q = $request->query('q')) {
            $query->where(fn($q2) => $q2->where('display_name', 'like', "%{$q}%")
                                         ->orWhere('email', 'like', "%{$q}%"));
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Comptes organisation (docs/fonctionnalites/comptes-organisation.md)
        if ($accountType = $request->query('account_type')) {
            $query->where('account_type', $accountType);
        }

        if ($verification = $request->query('verification_status')) {
            $query->where('verification_status', $verification);
        }

        $users = $query->latest()->paginate(20);

        return $this->paginated(
            UserResource::collection($users->items()),
            [
                'total'                 => $users->total(),
                'pending_organizations' => User::pendingVerification()->count(),
            ]
        );
    }

    public function verifyOrganization(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->notFound();
        if (!$user->isOrganization()) {
            return $this->error('Seul un compte organisation peut être vérifié', 422);
        }

        OrganizationAccounts::verify($user, $request->user());

        return $this->success(new UserResource($user->fresh()), 'Organisation vérifiée');
    }

    public function rejectOrganization(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $user = User::find($id);
        if (!$user) return $this->notFound();
        if (!$user->isOrganization()) {
            return $this->error('Seul un compte organisation peut faire l’objet d’une vérification', 422);
        }

        OrganizationAccounts::reject($user, $request->user(), $request->input('reason'));

        return $this->success(new UserResource($user->fresh()), 'Vérification refusée');
    }

    public function showUser(string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->notFound();

        return $this->success(new UserResource($user));
    }

    public function banUser(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->notFound();
        if ($user->isAdmin()) return $this->forbidden('Impossible de bannir un administrateur');

        $user->update(['status' => 'banned', 'is_active' => false]);
        $user->tokens()->delete();

        return $this->success(null, 'Utilisateur banni');
    }

    public function suspendUser(Request $request, string $id): JsonResponse
    {
        $request->validate(['duration_days' => 'nullable|integer|min:1|max:365']);

        $user = User::find($id);
        if (!$user) return $this->notFound();
        if ($user->isAdmin()) return $this->forbidden();

        $user->update(['status' => 'suspended']);

        return $this->success(null, 'Utilisateur suspendu');
    }

    public function activateUser(string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->notFound();

        $user->update(['status' => 'active', 'is_active' => true]);

        return $this->success(null, 'Utilisateur activé');
    }

    public function updateUserRole(Request $request, string $id): JsonResponse
    {
        $request->validate(['role' => 'required|in:utilisateur,moderateur,administrateur']);

        if ($request->user()->id === $id) {
            return $this->forbidden('Vous ne pouvez pas modifier votre propre rôle');
        }

        $user = User::find($id);
        if (!$user) return $this->notFound();

        $user->update(['role' => $request->role]);

        return $this->success(null, 'Rôle mis à jour');
    }

    public function content(Request $request): JsonResponse
    {
        $query = Testimony::with('user')->withoutJournal(); // carnet privé exclu

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $testimonies = $query->latest()->paginate(20);

        return $this->paginated(
            TestimonyResource::collection($testimonies->items()),
            ['total' => $testimonies->total()]
        );
    }

    public function categories(Request $request): JsonResponse
    {
        return $this->success(CategoryResource::collection(Category::orderBy('display_order')->get()));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:50',
            'slug'          => 'nullable|string|regex:/^[a-z0-9_-]+$/|max:50',
            'icon'          => 'nullable|string',
            'color'         => 'nullable|string|max:10',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $slug = $request->slug ?? Str::slug($request->name, '_');

        if (Category::where('slug', $slug)->exists()) {
            return $this->error("Le slug '{$slug}' est déjà utilisé. Fournissez un slug personnalisé.", 422);
        }

        $category = Category::create([
            'name'          => $request->name,
            'slug'          => $slug,
            'icon'          => $request->icon,
            'color'         => $request->color,
            'display_order' => $request->display_order ?? (Category::max('display_order') + 1),
        ]);

        return $this->created(new CategoryResource($category), 'Catégorie créée');
    }

    public function updateCategory(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);
        if (!$category) return $this->notFound();

        $request->validate([
            'name'          => 'nullable|string|max:50',
            'icon'          => 'nullable|string',
            'color'         => 'nullable|string|max:10',
            'display_order' => 'nullable|integer|min:0',
            'is_active'     => 'nullable|boolean',
        ]);

        $category->update($request->only(['name', 'icon', 'color', 'display_order', 'is_active']));

        return $this->success(new CategoryResource($category), 'Catégorie mise à jour');
    }

    public function deleteCategory(string $id): JsonResponse
    {
        $category = Category::find($id);
        if (!$category) return $this->notFound();

        if ($category->testimonies()->exists()) {
            return $this->error(
                "Impossible de supprimer : {$category->testimony_count} témoignage(s) sont liés à cette catégorie. Désactivez-la plutôt.",
                422
            );
        }

        $category->delete();
        return $this->success(null, 'Catégorie supprimée');
    }

    public function settings(): JsonResponse
    {
        return $this->success(AppSetting::allGrouped());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        foreach ($request->all() as $key => $value) {
            AppSetting::set($key, $value);
        }

        return $this->success(AppSetting::allGrouped(), 'Paramètres mis à jour');
    }

    private function approvalRate(): float
    {
        $total    = ModerationLog::whereIn('action', ['approved', 'rejected'])->count();
        $approved = ModerationLog::where('action', 'approved')->count();

        return $total > 0 ? round(($approved / $total) * 100, 1) : 0.0;
    }
}

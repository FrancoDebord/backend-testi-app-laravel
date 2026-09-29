<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Comment;
use App\Models\ModerationLog;
use App\Models\Testimony;
use App\Models\User;
use App\Services\OrganizationAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $today = now()->toDateString();

        $stats = [
            'totalUsers'          => User::count(),
            'newUsersToday'       => User::whereDate('created_at', $today)->count(),
            'totalTestimonies'    => Testimony::count(),
            'pendingTestimonies'  => Testimony::pending()->count(),
            'approvedTestimonies' => Testimony::where('status', 'approved')->count(),
            'viewsThisMonth'      => Testimony::whereMonth('created_at', now()->month)->sum('views_count'),
            'commentsThisMonth'   => Comment::whereMonth('created_at', now()->month)->count(),
            'approvalRate'        => $this->approvalRate(),
            'pendingOrganizations' => User::pendingVerification()->count(),
            'totalViews'          => (int) Testimony::withoutJournal()->sum('views_count'),
        ];

        $recentTestimonies  = Testimony::with(['user', 'category'])->withoutJournal()->latest()->limit(5)->get();
        $recentUsers        = User::latest()->limit(5)->get();
        // Tableau de bord aux couleurs de la charte : docs/interface.md, docs/fonctionnalites/administration.md
        $pendingItems       = Testimony::with('user')->pending()->oldest()->limit(4)->get();
        $popularTestimonies = Testimony::with('user')->published()->orderByDesc('views_count')->limit(5)->get();
        $popularCategories  = Category::where('is_active', true)
            ->withCount(['testimonies as published_count' => fn ($q) => $q->published()])
            ->orderByDesc('published_count')->orderBy('display_order')
            ->limit(6)->get();
        $activity           = \App\Support\WeeklyActivity::lastDays()['days'];

        return view('admin.dashboard', compact(
            'stats', 'recentTestimonies', 'recentUsers', 'pendingItems', 'popularTestimonies', 'popularCategories', 'activity'
        ));
    }

    public function users(Request $request): View
    {
        $query = User::query();

        if ($q = $request->query('q')) {
            $query->where(fn($q2) => $q2->where('display_name', 'like', "%{$q}%")
                                         ->orWhere('email', 'like', "%{$q}%"));
        }

        if ($role = $request->query('role')) $query->where('role', $role);
        if ($status = $request->query('status')) $query->where('status', $status);

        // Onglets « Organisations » (docs/fonctionnalites/comptes-organisation.md)
        $tab = match ($request->query('tab')) {
            'organizations' => 'organizations',
            'pending'       => 'pending',
            default         => 'all',
        };
        if ($tab === 'organizations') $query->organizations();
        if ($tab === 'pending')       $query->pendingVerification();

        $users = $query->latest()->paginate(20);
        $pendingOrganizations = User::pendingVerification()->count();

        return view('admin.users.index', compact('users', 'tab', 'pendingOrganizations'));
    }

    public function verifyOrganization(string $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        abort_unless($user->isOrganization(), 422, 'Seul un compte organisation peut être vérifié.');

        OrganizationAccounts::verify($user, Auth::user());

        return back()->with('success', 'Organisation vérifiée. Le compte a été prévenu.');
    }

    public function rejectOrganization(Request $request, string $id): RedirectResponse
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        $user = User::findOrFail($id);
        abort_unless($user->isOrganization(), 422, 'Seul un compte organisation peut faire l’objet d’une vérification.');

        OrganizationAccounts::reject($user, Auth::user(), $request->input('reason'));

        return back()->with('success', 'Vérification refusée. Le compte a été prévenu.');
    }

    public function showUser(string $id): View
    {
        $user        = User::with('verifier')->findOrFail($id);
        $testimonies = Testimony::where('user_id', $id)->withoutJournal()->latest()->limit(10)->get();

        return view('admin.users.show', compact('user', 'testimonies'));
    }

    public function updateUser(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'role'   => 'required|in:visiteur,utilisateur,moderateur,administrateur',
            'status' => 'required|in:active,suspended,banned',
        ]);

        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return back()->withErrors(['error' => 'Vous ne pouvez pas modifier votre propre rôle.']);
        }

        $user->update([
            'role'      => $request->role,
            'status'    => $request->status,
            'is_active' => $request->status === 'active',
        ]);

        if ($request->status === 'banned') {
            $user->tokens()->delete();
        }

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function content(Request $request): View
    {
        $query = Testimony::with('user')->withoutJournal(); // carnet privé exclu

        if ($status = $request->query('status')) $query->where('status', $status);
        if ($type = $request->query('type')) $query->where('type', $type);
        if ($q = $request->query('q')) {
            $query->where(fn($q2) => $q2->where('title', 'like', "%{$q}%"));
        }

        $testimonies = $query->latest()->paginate(20);

        return view('admin.content.index', compact('testimonies'));
    }

    public function categories(Request $request): View
    {
        $categories = Category::orderBy('display_order')->paginate(20);
        return view('admin.categories.index', compact('categories'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:50',
            'slug'          => 'required|string|unique:categories,slug|max:30',
            'icon'          => 'nullable|string',
            'color'         => 'nullable|string|max:10',
            'display_order' => 'nullable|integer',
        ]);

        Category::create($data);
        return back()->with('success', 'Catégorie créée.');
    }

    public function updateCategory(Request $request, string $id): RedirectResponse
    {
        $category = Category::findOrFail($id);
        $data     = $request->validate([
            'name'          => 'required|string|max:50',
            'icon'          => 'nullable|string',
            'color'         => 'nullable|string|max:10',
            'display_order' => 'nullable|integer',
            'is_active'     => 'nullable|boolean',
        ]);

        $category->update($data + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Catégorie mise à jour.');
    }

    public function deleteCategory(string $id): RedirectResponse
    {
        Category::findOrFail($id)->delete();
        return back()->with('success', 'Catégorie supprimée.');
    }

    public function settings(): View
    {
        $settings = AppSetting::orderBy('group')->orderBy('key')->get()->groupBy('group');
        return view('admin.settings', compact('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        foreach ($request->except(['_token', '_method']) as $key => $value) {
            AppSetting::set($key, $value);
        }

        return back()->with('success', 'Paramètres mis à jour.');
    }

    private function approvalRate(): float
    {
        $total    = ModerationLog::whereIn('action', ['approved', 'rejected'])->count();
        $approved = ModerationLog::where('action', 'approved')->count();
        return $total > 0 ? round(($approved / $total) * 100, 1) : 0.0;
    }
}

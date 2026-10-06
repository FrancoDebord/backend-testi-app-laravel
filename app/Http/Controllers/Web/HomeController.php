<?php

namespace App\Http\Controllers\Web;

use App\Enums\TestimonyStatus;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DailyVerse;
use App\Models\LiveSession;
use App\Models\Testimony;
use App\Models\User;
use App\Services\PrayerRequests;
use App\Support\WeeklyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Accueil (charte ARISE & SHINE Krea, maquette « Témoignages de Gloire ») :
 * bandeau et chiffres clés, actions rapides, témoignages récents, catégories, statistiques,
 * plus populaires, à la une, shorts ; modération et gestion des contenus pour l'équipe,
 * « Mes témoignages » pour une personne connectée. Voir docs/fonctionnalites/accueil.md
 */
class HomeController extends Controller
{
    /** Onglets du bloc « Gestion des contenus » (modération). */
    public const CONTENT_TABS = [
        ''         => 'Tous',
        'pending'  => 'En attente',
        'approved' => 'Approuvés',
        'rejected' => 'Rejetés',
    ];

    public function index(Request $request): View
    {
        $category = $request->query('category');
        $type     = $request->query('type');
        $viewer   = $request->user();

        $feedQuery = Testimony::with(['user', 'category'])
            ->published()
            ->latest();

        if ($category) $feedQuery->ofCategory($category);
        if ($type) $feedQuery->where('type', $type);

        $feed       = $feedQuery->paginate(12);
        $categories = Category::active()->get();

        // Blocs de la page d'accueil : première page, sans filtre.
        $showShelves = !$category && !$type && $feed->onFirstPage();
        // Requêtes de prière publiques récentes, insérées dans « Témoignages récents » (sans filtre).
        $inserts = !$category && !$type
            ? app(PrayerRequests::class)->list($viewer, 'feed')->limit(10)->get()
            : collect();
        $data = compact('feed', 'categories', 'category', 'type', 'showShelves', 'inserts');

        if (!$showShelves) {
            return view('home.index', $data + [
                'featured' => collect(), 'lives' => collect(), 'shorts' => collect(), 'verse' => null,
            ]);
        }

        $data += [
            'featured' => Testimony::with(['user', 'category'])->featured()->latestPublished()->limit(5)->get(),
            'verse'    => DailyVerse::today(),
            'lives'    => LiveSession::with('host')->onAir()->latest('started_at')->limit(4)->get(),
            'shorts'   => Testimony::with('user')->published()->shorts()->latestPublished()->limit(12)->get(),
            // Chiffres publics, relus au plus toutes les 5 minutes.
            'stats'    => Cache::remember('home.stats', 300, fn () => [
                'testimonies' => Testimony::published()->count(),
                'users'       => User::where('status', UserAccountStatus::Active->value)->count(),
                'views'       => (int) Testimony::published()->sum('views_count'),
                'prayers'     => (int) Testimony::published()->sum('prayer_count'),
            ]),
            'activity' => Cache::remember('home.activity', 300, fn () => WeeklyActivity::lastDays()),
            'popular'  => Testimony::with('user')->published()->orderByDesc('views_count')->limit(5)->get(),
            'popularCategories' => Category::active()
                ->withCount(['testimonies as published_count' => fn ($q) => $q->published()])
                ->reorder()->orderByDesc('published_count')->orderBy('display_order')
                ->limit(6)->get(),
        ];

        // Équipe de modération : file d'attente et gestion des contenus.
        if ($viewer?->canModerate()) {
            $tab = array_key_exists((string) $request->query('gestion'), self::CONTENT_TABS) ? (string) $request->query('gestion') : '';
            $data += [
                'pendingCount' => Testimony::pending()->count(),
                'pendingItems' => Testimony::with('user')->pending()->oldest()->limit(4)->get(),
                'contentTab'   => $tab,
                'contentItems' => Testimony::with(['user', 'category'])->withoutJournal()
                    ->when($tab !== '', fn ($q) => $q->where('status', TestimonyStatus::from($tab)))
                    ->latest()->limit(5)->get(),
            ];
        } elseif ($viewer) {
            // Personne connectée : ses derniers témoignages, tous statuts (hors carnet privé).
            $data += [
                'myItems' => Testimony::with('category')->where('user_id', $viewer->id)->withoutJournal()->latest()->limit(5)->get(),
            ];
        }

        return view('home.index', $data);
    }
}

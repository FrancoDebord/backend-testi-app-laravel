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
 * Accueil (charte ARISE & SHINE Krea, maquette « My Testimony ») :
 * bandeau et chiffres clés, actions rapides, témoignages récents, catégories, statistiques,
 * plus populaires, à la une, shorts ; modération et gestion des contenus pour l'équipe,
 * « Mes témoignages » pour une personne connectée. Voir docs/fonctionnalites/accueil.md
 */
class HomeController extends Controller
{
    /** Onglets du bloc « Gestion des contenus » (modération). */
    public static function contentTabs(): array
    {
        return [
            ''         => __('discover.home.content_tabs.all'),
            'pending'  => __('discover.home.content_tabs.pending'),
            'approved' => __('discover.home.content_tabs.approved'),
            'rejected' => __('discover.home.content_tabs.rejected'),
        ];
    }

    public function index(Request $request): View
    {
        $category = $request->query('category');
        $type     = $request->query('type');
        $viewer   = $request->user();

        $feedQuery = Testimony::with(['user', 'category'])
            ->published()
            ->latestPublished()->orderByDesc('id'); // du plus récemment publié au plus ancien

        if ($category) $feedQuery->ofCategory($category);
        if ($type) $feedQuery->where('type', $type);

        $feed       = $feedQuery->paginate(12);
        $categories = Category::active()->get();

        // Blocs de la page d'accueil : première page, sans filtre.
        $showShelves = !$category && !$type && $feed->onFirstPage();
        // Requêtes de prière publiques récentes : section « Prières récentes », séparée des témoignages
        // pour que les cartes de la grille gardent la même hauteur.
        $recentPrayers = $showShelves
            // les plus proches d'abord (zone de la personne ou langue du navigateur : localisation.md)
            ? app(\App\Services\FeedInserts::class)->rankByPlace('prayerRequests', app(PrayerRequests::class)->list($viewer, 'feed')->limit(30)->get(), $viewer, 6)
            : collect();
        $data = compact('feed', 'categories', 'category', 'type', 'showShelves', 'recentPrayers');

        if (!$showShelves) {
            return view('home.index', $data + [
                'featured' => collect(), 'lives' => collect(), 'shorts' => collect(), 'verse' => null, 'prayerSessions' => collect(),
            ]);
        }

        $data += [
            // À la une : même liste que GET /testimonies/featured (docs/fonctionnalites/gestion-des-temoignages.md)
            'featured' => app(\App\Services\FeaturedTestimonies::class)->list(5, ['user', 'category']),
            'verse'    => DailyVerse::today(),
            // Directs à l'antenne visibles de la personne (comme « Mon fil » et l'application).
            'lives'    => LiveSession::with('host')->onAir()->latest('started_at')->limit(8)->get()
                ->filter(fn ($l) => $l->isVisibleTo($viewer))->take(4)->values(),
            // Sessions de prière programmées (bandeau masqué s'il n'y en a pas) : docs/fonctionnalites/sessions-de-priere.md
            'prayerSessions' => app(\App\Services\PrayerSessions::class)->list($viewer, 'upcoming')->limit(6)->get(),
            'shorts'   => Testimony::with('user')->published()->shorts()->latestPublished()->limit(12)->get(),
            // Témoignages de conversion (bloc masqué s'il n'y en a pas) : docs/fonctionnalites/temoignage-de-conversion.md
            'conversions' => Testimony::with('user')->published()->conversion()->latestPublished()->limit(3)->get(),
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
            $tab = array_key_exists((string) $request->query('gestion'), self::contentTabs()) ? (string) $request->query('gestion') : '';
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

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DailyVerse;
use App\Models\ThemeSemaine;
use App\Services\Sitemaps;
use App\Services\VerseDesignOfTheDay;
use App\Support\ShareMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Pages publiques du référencement : catégories de témoignages, thèmes du verset du jour, sitemaps et
 * robots.txt. Voir docs/fonctionnalites/referencement.md
 */
class PublicPagesController extends Controller
{
    /** GET /temoignages/categorie/{slug} : introduction et témoignages publiés de la catégorie, les plus récents d'abord. */
    public function category(Request $request, string $slug): View
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $testimonies = Sitemaps::testimonyQuery()->with(['user', 'category'])->where('category_slug', $category->slug)
            ->latest('approved_at')->paginate((int) config('seo.per_page', 24))->withQueryString();

        return view('seo.category', [
            'category'    => $category,
            'testimonies' => $testimonies,
            'canonical'   => ShareMeta::absolute(route('testimonies.category', array_filter(['slug' => $slug, 'page' => $testimonies->currentPage() > 1 ? $testimonies->currentPage() : null]), false)),
            'categories'  => Category::where('is_active', true)->orderBy('display_order')->get(),
        ]);
    }

    /**
     * GET /themes/{slug} : thème du verset du jour (la semaine la plus récente déjà commencée qui porte ce thème),
     * ses versets des jours passés et les derniers témoignages. Thème à venir ou inconnu : 404.
     */
    public function theme(string $slug): View
    {
        $today = VerseDesignOfTheDay::todayDate();
        $theme = ThemeSemaine::where('slug', $slug)->whereDate('date_debut', '<=', $today)->orderByDesc('date_debut')->firstOrFail();
        $verses = DailyVerse::where('theme_semaine_id', $theme->id)->where('is_active', true)
            ->whereDate('scheduled_date', '<=', $today)->orderBy('scheduled_date')->get();

        return view('seo.theme', [
            'theme'       => $theme,
            'verses'      => $verses,
            'testimonies' => Sitemaps::testimonyQuery()->with(['user', 'category'])->latest('approved_at')->limit(6)->get(),
            'canonical'   => ShareMeta::absolute(route('themes.show', $theme->slug, false)),
        ]);
    }

    /** GET /sitemap.xml (index) et /sitemap-{name}.xml. */
    public function sitemap(Sitemaps $sitemaps, string $name = 'index'): Response
    {
        abort_unless($name === 'index' || in_array($name, Sitemaps::NAMES, true), 404);

        return response($sitemaps->xml($name), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** GET /robots.txt : espaces privés et API bloqués, adresse du sitemap. */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            // Espaces de compte, d'administration, de recherche et API (adresses réelles du site).
            'Disallow: /admin',
            'Disallow: /moderation',
            'Disallow: /api/',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /settings',
            'Disallow: /profile',
            'Disallow: /profiles/',
            'Disallow: /personnes',
            'Disallow: /explore',
            'Disallow: /mes-temoignages',
            'Disallow: /mon-fil',
            'Disallow: /carnet',
            'Disallow: /notifications',
            'Disallow: /publish',
            'Disallow: /tableau-de-bord',
            'Disallow: /comptes',
            'Disallow: /verifications',
            'Disallow: /demandes-suppression',
            'Disallow: /actions-groupees',
            'Allow: /',
            '',
            'Sitemap: ' . ShareMeta::absolute('/sitemap.xml'),
        ];

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

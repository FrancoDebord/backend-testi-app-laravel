<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Affiche la documentation du serveur (fichiers Markdown de docs/) en HTML.
 * Réservé aux administrateurs. Voir docs/fonctionnalites/documentation-en-ligne.md
 */
class DocumentationController extends Controller
{
    public function show(?string $page = null): View
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $pages = $this->pages();
        $key   = $page ?? 'README';

        // Seuls les fichiers réellement présents dans docs/ peuvent être lus.
        abort_unless(isset($pages[$key]), 404);

        $markdown = File::get($pages[$key]['path']);
        $title    = $pages[$key]['title'];
        // Le titre principal est affiché dans l'en-tête de la page : on le retire du corps.
        $markdown = preg_replace('/\A\s*#\s+[^\n]*\n/u', '', $markdown, 1);

        $html = Str::markdown($markdown, [
            'html_input'         => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return view('admin.documentation', [
            'pages'   => $pages,
            'current' => $key,
            'title'   => $title,
            'html'    => $this->enhance($html, $key, $pages),
            'updated' => date('d/m/Y à H:i', File::lastModified($pages[$key]['path'])),
        ]);
    }

    /**
     * Pages disponibles, dans l'ordre du menu : accueil, interface, fonctionnalités, journal.
     *
     * @return array<string, array{path: string, title: string, group: string}>
     */
    private function pages(): array
    {
        $root  = base_path('docs');
        $pages = [];

        foreach (File::allFiles($root) as $file) {
            if (strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $key = str_replace('\\', '/', substr($file->getRelativePathname(), 0, -3));
            if (!preg_match('#^[A-Za-z0-9\-_/]+$#', $key)) {
                continue;
            }
            preg_match('/^#\s+(.+)$/mu', File::get($file->getPathname()), $m);

            $pages[$key] = [
                'path'  => $file->getPathname(),
                'title' => trim($m[1] ?? Str::headline(basename($key))),
                'group' => match (true) {
                    $key === 'README'                      => 'Général',
                    str_starts_with($key, 'fonctionnalites/') => 'Fonctionnalités',
                    $key === 'journal-des-modifications'   => 'Suivi',
                    default                                => 'Général',
                },
            ];
        }

        $order = fn (string $k) => match (true) {
            $k === 'README'                          => '0',
            $k === 'journal-des-modifications'       => '9',
            str_starts_with($k, 'fonctionnalites/')  => '5' . $k,
            default                                  => '1' . $k,
        };
        uksort($pages, fn ($a, $b) => strcmp($order($a), $order($b)));

        return $pages;
    }

    /** Ancres des titres, liens internes vers les autres pages, tableaux défilants. */
    private function enhance(string $html, string $current, array $pages): string
    {
        // Identifiants des titres, au format GitHub (« 4. Déploiement » → « 4-déploiement »).
        $html = preg_replace_callback('#<h([2-4])>(.*?)</h\1>#su', function ($m) {
            $slug = trim(preg_replace('/[^\p{L}\p{N}\s\-]/u', '', mb_strtolower(strip_tags(html_entity_decode($m[2])))));
            $slug = preg_replace('/\s/u', '-', $slug);
            return "<h{$m[1]} id=\"" . e($slug) . "\">{$m[2]}</h{$m[1]}>";
        }, $html);

        // Liens « autre-page.md#ancre » → route de la documentation.
        $baseDir = str_contains($current, '/') ? dirname($current) : '';
        $html = preg_replace_callback('#href="([^":\#]+)\.md(\#[^"]*)?"#u', function ($m) use ($baseDir, $pages) {
            $target = $this->normalize(($baseDir !== '' ? $baseDir . '/' : '') . rawurldecode($m[1]));
            if (!isset($pages[$target])) {
                return $m[0];
            }
            $url = $target === 'README'
                ? route('admin.documentation')
                : route('admin.documentation', ['page' => $target]);
            return 'href="' . e($url) . ($m[2] ?? '') . '"';
        }, $html);

        // Les tableaux larges défilent dans leur cadre, jamais la page.
        return str_replace(['<table>', '</table>'], ['<div class="doc-table"><table>', '</table></div>'], $html);
    }

    private function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') { array_pop($parts); continue; }
            $parts[] = $segment;
        }
        return implode('/', $parts);
    }
}

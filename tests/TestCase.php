<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Garde-fou : les tests ne tournent que sur la base SQLite en mémoire de phpunit.xml. Avec la configuration
     * en cache (php artisan optimize, config:cache), les variables de phpunit.xml sont ignorées et RefreshDatabase
     * viderait la vraie base (incident du 2026-10-27). Vérifié avant toute migration.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");
        if ($app->configurationIsCached() || $connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(sprintf(
                "Tests arrêtés : base « %s » (%s) au lieu de SQLite en mémoire%s. Ils videraient cette base. "
                . "Lancez « php artisan optimize:clear », puis relancez les tests.",
                $database, $connection, $app->configurationIsCached() ? ', configuration en cache' : '',
            ));
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Requêtes sans Accept-Language, comme l'application mobile (Symfony ajouterait « en-us » par défaut) :
        // l'API répond alors en français (docs/fonctionnalites/langues.md).
        $this->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => '']);
        // Temps de prière désactivé par défaut : les tests qui prient n'attendent pas 5 min.
        // Tests\Feature\PrayerFocusTest l'active (docs/fonctionnalites/temps-de-priere.md).
        config(['prayer.focus_seconds' => 0]);
    }
}

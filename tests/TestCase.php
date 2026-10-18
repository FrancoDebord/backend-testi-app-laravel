<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    
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

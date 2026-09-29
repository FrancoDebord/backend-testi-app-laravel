<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppLinksTest extends TestCase
{
    public function test_android_asset_links_declare_the_app_and_its_fingerprints(): void
    {
        config([
            'applinks.android.package' => 'com.airid.testi_app',
            'applinks.android.sha256_fingerprints' => ['AA:BB', 'CC:DD'],
        ]);

        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([[
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target'   => [
                    'namespace'                => 'android_app',
                    'package_name'             => 'com.airid.testi_app',
                    'sha256_cert_fingerprints' => ['AA:BB', 'CC:DD'],
                ],
            ]]);
    }

    public function test_apple_association_is_empty_until_the_ios_app_id_is_set(): void
    {
        config(['applinks.ios.app_id' => null]);
        $this->get('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertExactJson(['applinks' => ['apps' => [], 'details' => []]]);

        config(['applinks.ios.app_id' => 'ABCDE12345.com.airid.testiApp', 'applinks.paths' => ['/testimonies/*']]);
        $this->get('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertExactJson(['applinks' => ['apps' => [], 'details' => [[
                'appIDs'     => ['ABCDE12345.com.airid.testiApp'],
                'components' => [['/' => '/testimonies/*']],
            ]]]]);
    }
}

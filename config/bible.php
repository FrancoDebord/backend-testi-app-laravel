<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sources API
    |--------------------------------------------------------------------------
    */

    'apibible' => [
        'base_url' => 'https://api.scripture.api.bible/v1',
        'key'      => env('BIBLE_API_KEY', ''),
    ],

    'biblebrain' => [
        'base_url' => 'https://4.dbt.io/api',
        'key'      => env('BIBLEBRAIN_API_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registre des traductions
    |--------------------------------------------------------------------------
    | source     : 'apibible' | 'biblebrain'
    | bible_id   : identifiant Bible sur API.Bible
    | fileset_id : identifiant fileset sur BibleBrain (type text_plain)
    |
    | Pour découvrir les IDs manquants :
    |   php artisan bible:sources --source=apibible  --lang=fr
    |   php artisan bible:sources --source=biblebrain --lang=fon
    */

    'translations' => [

        // ── Français ──────────────────────────────────────────────────────────
        'LSG' => [
            'name'      => 'Louis Segond 1910',
            'language'  => 'fr',
            'lang_name' => 'Français',
            'source'    => 'apibible',
            'bible_id'  => env('BIBLE_ID_LSG', 'bba9f40183526463-01'),
        ],
        'OST' => [
            'name'      => 'Ostervald (1996)',
            'language'  => 'fr',
            'lang_name' => 'Français',
            'source'    => 'apibible',
            'bible_id'  => env('BIBLE_ID_OST'),
        ],

        // ── Anglais ───────────────────────────────────────────────────────────
        'KJV' => [
            'name'      => 'King James Version',
            'language'  => 'en',
            'lang_name' => 'English',
            'source'    => 'apibible',
            'bible_id'  => env('BIBLE_ID_KJV', 'de4e12af7f28f599-01'),
        ],
        'WEB' => [
            'name'      => 'World English Bible',
            'language'  => 'en',
            'lang_name' => 'English',
            'source'    => 'apibible',
            'bible_id'  => env('BIBLE_ID_WEB', '9879dbb7cfe39e4d-01'),
        ],
        'ASV' => [
            'name'      => 'American Standard Version',
            'language'  => 'en',
            'lang_name' => 'English',
            'source'    => 'apibible',
            'bible_id'  => env('BIBLE_ID_ASV', '06125adad2d5898a-01'),
        ],

        // ── Yoruba ────────────────────────────────────────────────────────────
        'YORUBA' => [
            'name'      => 'Bíbélì Mímọ́ Yorùbá',
            'language'  => 'yo',
            'lang_name' => 'Yoruba',
            'source'    => 'apibible',
            'bible_id'  => env('BIBLE_ID_YCB'), // Trouver avec : bible:sources --source=apibible --lang=yo
        ],
        'YORUBA2010' => [
            'name'      => 'Bíbélì Mímọ́ Yorùbá 2010',
            'language'  => 'yo',
            'lang_name' => 'Yoruba',
            'source'    => 'apibible',
            'bible_id'  => env('BIBLE_ID_YCB'), // Trouver avec : bible:sources --source=apibible --lang=yo
        ],
        'YORUBA2014' => [
            'name'      => 'Bíbélì Mímọ́ Yorùbá 2014',
            'language'  => 'yo',
            'lang_name' => 'Yoruba',
            'source'    => 'apibible',
            'bible_id'  => env('BIBLE_ID_YCB'), // Trouver avec : bible:sources --source=apibible --lang=yo
        ],

        // ── Fongbe ────────────────────────────────────────────────────────────
        'FONGBE' => [
            'name'       => 'Bible Fongbe',
            'language'   => 'fon',
            'lang_name'  => 'Fongbe',
            'source'     => 'biblebrain',
            'fileset_id' => env('BIBLEBRAIN_FILESET_FON'), // Trouver avec : bible:sources --source=biblebrain --lang=fon
        ],

        // ── Gun-Gbe ───────────────────────────────────────────────────────────
        'GUN' => [
            'name'       => 'Bible Gun-Gbe',
            'language'   => 'gun',
            'lang_name'  => 'Gun-Gbe',
            'source'     => 'biblebrain',
            'fileset_id' => env('BIBLEBRAIN_FILESET_GUN'), // Trouver avec : bible:sources --source=biblebrain --lang=gun
        ],
        'AMPLIFIED' => [
            'name'       => 'Amplified Bible ',
            'language'   => 'en',
            'lang_name'  => 'English',
        ],
        'BBE' => [
            'name'       => 'BBE ',
            'language'   => 'en',
            'lang_name'  => 'English',
        ],
        'BDS' => [
            'name'       => 'Bible Du Semeur ',
            'language'   => 'fr',
            'lang_name'  => 'Français',
        ],
        'BFC' => [
            'name'       => 'Bible en Français Courant ',
            'language'   => 'fr',
            'lang_name'  => 'Français',
        ],
        'EN_ASV' => [
            'name'       => 'American Standard Version ',
            'language'   => 'en',
            'lang_name'  => 'English',
        ],
        'EN_CSB' => [
            'name'       => 'Christian Standard Bible ',
            'language'   => 'en',
            'lang_name'  => 'English',
        ],
        'EN_DARBY' => [
            'name'       => 'English Darby ',
            'language'   => 'en',
            'lang_name'  => 'English',
        ],
        'EN_ERV' => [
            'name'       => 'Easy-to-Read Version ',
            'language'   => 'en',
            'lang_name'  => 'English',
        ],
        'EN_ESV' => [
            'name'       => 'English Standard Version',
            'language'   => 'en',
            'lang_name'  => 'English',
        ],
        'EN_KJ' => [
            'name'       => 'King James',
            'language'   => 'en',
            'lang_name'  => 'English',
        ],
        'EN_NIV' => [
            'name'       => 'New International Version',
            'language'   => 'en',
            'lang_name'  => 'English',
        ],
        'EN_NKJ' => [
            'name'       => 'New King James',
            'language'   => 'en',
            'lang_name'  => 'English',
        ],
        'EN_NLT' => [
            'name'       => 'New Living Translation',
            'language'   => 'en',
            'lang_name'  => 'English',
        ],
        'EWE' => [
            'name'       => 'Bible en EWE',
            'language'   => 'ewe',
            'lang_name'  => 'Ewe',
        ],
        'FR_DARBY' => [
            'name'       => 'Bible Darby en Français',
            'language'   => 'fr',
            'lang_name'  => 'Français',
        ],
        'FR_MARTIN' => [
            'name'       => 'Bible Version Martin',
            'language'   => 'fr',
            'lang_name'  => 'Français',
        ],
        'FR_S21' => [
            'name'       => 'Bible Segond 21',
            'language'   => 'fr',
            'lang_name'  => 'Français',
        ],
        'PDV2017' => [
            'name'       => 'Parole De Vie 2017',
            'language'   => 'fr',
            'lang_name'  => 'Français',
        ],

    ],
];

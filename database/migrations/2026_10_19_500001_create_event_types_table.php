<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Types d'événements gérés par les administrateurs (docs/fonctionnalites/types-evenements.md).
 * Remplace l'énumération App\Enums\EventType : `events.type` garde le nom court (slug), sans clé étrangère.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 40)->unique();
            $table->string('name_fr', 80);
            $table->string('name_en', 80)->nullable();
            $table->string('icon', 30)->default('calendar');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Les 8 types de l'ancienne énumération, mêmes noms courts (les événements existants les utilisent).
        $now  = now();
        $rows = [
            ['crusade',    'Croisade d\'évangélisation', 'Evangelistic crusade', 'bullhorn'],
            ['conference', 'Conférence',                 'Conference',           'microphone'],
            ['seminar',    'Séminaire',                  'Seminar',              'chalkboard'],
            ['camp',       'Camp',                       'Camp',                 'campground'],
            ['tour',       'Tournée',                    'Tour',                 'route'],
            ['worship',    'Concert de louange',         'Worship concert',      'music'],
            ['retreat',    'Retraite spirituelle',       'Spiritual retreat',    'dove'],
            ['other',      'Autre événement',            'Other event',          'calendar'],
        ];
        DB::table('event_types')->insert(collect($rows)->map(fn ($r, $i) => [
            'slug' => $r[0], 'name_fr' => $r[1], 'name_en' => $r[2], 'icon' => $r[3],
            'position' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->tinyInteger('last_bible_book')->unsigned()->nullable()->after('language');
            $table->smallInteger('last_bible_chapter')->unsigned()->nullable()->after('last_bible_book');
            $table->string('last_bible_translation', 10)->nullable()->after('last_bible_chapter');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['last_bible_book', 'last_bible_chapter', 'last_bible_translation']);
        });
    }
};

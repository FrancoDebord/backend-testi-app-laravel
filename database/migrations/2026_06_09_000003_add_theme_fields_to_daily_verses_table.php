<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_verses', function (Blueprint $table) {
            $table->string('theme', 50)->nullable()->after('is_active');      // ex: faithfulness, testimonies, promises, obedience
            $table->tinyInteger('week_number')->unsigned()->nullable()->after('theme'); // 1-4, rotation hebdomadaire
            $table->unsignedInteger('like_count')->default(0)->after('week_number');
            $table->unsignedInteger('prayer_count')->default(0)->after('like_count');
            $table->unsignedInteger('amen_count')->default(0)->after('prayer_count');
            $table->unsignedInteger('share_count')->default(0)->after('amen_count');
        });
    }

    public function down(): void
    {
        Schema::table('daily_verses', function (Blueprint $table) {
            $table->dropColumn(['theme', 'week_number', 'like_count', 'prayer_count', 'amen_count', 'share_count']);
        });
    }
};

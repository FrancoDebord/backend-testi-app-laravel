<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verset du jour : participation (docs/fonctionnalites/verset-du-jour.md).
 * - Requêtes de prière rattachées à un verset du jour (prayer_requests.daily_verse_id).
 * - Commentaires sous le verset, signalables (retrait automatique au 3e signalement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prayer_requests', function (Blueprint $table) {
            $table->foreignId('daily_verse_id')->nullable()->after('event_id')->constrained('daily_verses')->nullOnDelete();
            $table->index(['daily_verse_id', 'created_at']);
        });

        Schema::create('daily_verse_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('daily_verse_id')->constrained('daily_verses')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('report_count')->default(0);
            $table->timestamp('hidden_at')->nullable(); // retrait (signalements ou modération)
            $table->timestamps();
            $table->softDeletes();

            $table->index(['daily_verse_id', 'created_at']);
        });

        Schema::create('daily_verse_comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('daily_verse_comment_id')->constrained('daily_verse_comments')->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 50); // inappropriate_content | hate_speech | spam | other
            $table->timestamps();

            $table->unique(['daily_verse_comment_id', 'reporter_id'], 'dv_comment_reports_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_verse_comment_reports');
        Schema::dropIfExists('daily_verse_comments');
        Schema::table('prayer_requests', function (Blueprint $table) {
            $table->dropIndex(['daily_verse_id', 'created_at']);
            $table->dropConstrainedForeignId('daily_verse_id');
        });
    }
};

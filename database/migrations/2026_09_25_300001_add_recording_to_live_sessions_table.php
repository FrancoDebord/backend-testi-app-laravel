<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Enregistrement des directs → témoignage vidéo (docs/fonctionnalites/lives.md)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->boolean('record')->default(true)->after('comments_enabled');
            // none | recording | processing | ready | too_short | failed
            $table->string('recording_status', 20)->nullable()->after('record');
            $table->string('egress_id')->nullable()->index()->after('recording_status');
            $table->string('recording_path')->nullable()->after('egress_id');
            $table->unsignedInteger('recording_duration')->nullable()->after('recording_path');
            $table->text('recording_error')->nullable()->after('recording_duration');
            $table->foreignUuid('testimony_id')->nullable()->after('recording_error')->constrained('testimonies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('testimony_id');
            $table->dropColumn(['record', 'recording_status', 'egress_id', 'recording_path', 'recording_duration', 'recording_error']);
        });
    }
};

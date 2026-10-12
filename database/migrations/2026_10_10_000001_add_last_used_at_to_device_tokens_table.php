<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications push (docs/fonctionnalites/notifications-push.md) : date du dernier envoi réussi
 * ou du dernier enregistrement de l'appareil ; plateforme facultative.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('platform', 10)->nullable()->change();
            $table->timestamp('last_used_at')->nullable()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn('last_used_at');
        });
    }
};

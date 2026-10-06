<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct depuis une caméra IP ou un encodeur (LiveKit Ingress) : source, point d'entrée et clé.
 * Voir docs/fonctionnalites/lives-camera-ip.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            // browser : caméra de l'appareil du diffuseur ; rtmp : la caméra pousse le flux ; url : le serveur lit le flux
            $table->string('source', 10)->default('browser')->after('room_name');
            $table->string('ingress_id', 64)->nullable()->after('source');
            $table->string('ingress_url', 500)->nullable()->after('ingress_id');
            $table->text('ingress_stream_key')->nullable()->after('ingress_url'); // chiffrée
            $table->text('camera_url')->nullable()->after('ingress_stream_key');  // chiffrée (peut contenir un mot de passe)
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn(['source', 'ingress_id', 'ingress_url', 'ingress_stream_key', 'camera_url']);
        });
    }
};

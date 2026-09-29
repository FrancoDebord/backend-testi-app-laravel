<?php

use App\Services\FollowService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Abonnements : aucun compte ne se suit lui-même, compteurs exacts.
 * Voir docs/fonctionnalites/abonnements.md
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('follows')->whereColumn('follower_id', 'following_id')->delete();
        FollowService::recountAll();

        // Garde-fou en base (MySQL 8.0.16+ ; ignoré par les versions plus anciennes, absent sous SQLite).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE follows ADD CONSTRAINT follows_not_self CHECK (follower_id <> following_id)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE follows DROP CHECK follows_not_self');
        }
    }
};

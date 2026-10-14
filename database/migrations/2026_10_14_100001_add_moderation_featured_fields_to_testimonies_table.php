<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion des témoignages (docs/fonctionnalites/gestion-des-temoignages.md) :
 * - modération : moderated_by, moderated_at, rejection_reason (motif lisible, obligatoire au refus) ;
 * - label « Témoignage approuvé » : is_verified, verified_by, verified_at (administrateurs) ;
 * - mise à la une : featured_order, featured_from, featured_until (is_featured existe déjà) ;
 * - super-administrateur (suppression définitive) : users.is_super_admin, attribué en ligne de commande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->uuid('moderated_by')->nullable()->after('approved_at');
            $table->timestamp('moderated_at')->nullable()->after('moderated_by');
            $table->text('rejection_reason')->nullable()->after('moderated_at');
            $table->boolean('is_verified')->default(false)->after('is_featured');
            $table->uuid('verified_by')->nullable()->after('is_verified');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->integer('featured_order')->nullable()->after('verified_at');
            $table->timestamp('featured_from')->nullable()->after('featured_order');
            $table->timestamp('featured_until')->nullable()->after('featured_from');
            $table->index(['is_verified', 'status']);
            $table->index('deleted_at');
        });

        // Témoignages déjà modérés : dernière décision reprise du journal de modération.
        $last = DB::table('moderation_logs')->whereIn('action', ['approved', 'rejected'])
            ->orderBy('created_at')->get(['testimony_id', 'moderator_id', 'created_at']);
        foreach ($last->keyBy('testimony_id') as $testimonyId => $log) {
            DB::table('testimonies')->where('id', $testimonyId)
                ->update(['moderated_by' => $log->moderator_id, 'moderated_at' => $log->created_at]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->dropIndex(['is_verified', 'status']);
            $table->dropIndex(['deleted_at']);
            $table->dropColumn(['moderated_by', 'moderated_at', 'rejection_reason', 'is_verified', 'verified_by',
                'verified_at', 'featured_order', 'featured_from', 'featured_until']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_super_admin'));
    }
};

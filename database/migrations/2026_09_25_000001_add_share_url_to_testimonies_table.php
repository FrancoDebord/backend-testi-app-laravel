<?php

use App\Models\Testimony;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->string('share_url')->nullable()->after('cover_url');
        });

        // Témoignages existants (y compris supprimés) : URL construite à partir de APP_URL.
        DB::table('testimonies')->select('id')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('testimonies')->where('id', $row->id)->update(['share_url' => Testimony::shareUrlFor($row->id)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->dropColumn('share_url');
        });
    }
};

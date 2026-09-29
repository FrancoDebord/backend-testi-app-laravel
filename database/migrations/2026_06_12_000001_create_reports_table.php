<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('testimony_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('reason', 50); // inappropriate_content | false_testimony | hate_speech | spam | other
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            // Un seul signalement par utilisateur par témoignage
            $table->unique(['testimony_id', 'reporter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};

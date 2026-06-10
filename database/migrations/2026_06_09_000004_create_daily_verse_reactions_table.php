<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_verse_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_verse_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // like, pray, amen
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['daily_verse_id', 'user_id', 'type']);
            $table->index(['daily_verse_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_verse_reactions');
    }
};

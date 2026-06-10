<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verse_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('translation', 10);
            $table->tinyInteger('book')->unsigned();
            $table->smallInteger('chapter')->unsigned();
            $table->smallInteger('verse')->unsigned();
            $table->text('note');
            $table->timestamps();

            $table->unique(['user_id', 'translation', 'book', 'chapter', 'verse']);
            $table->index(['user_id', 'translation', 'book', 'chapter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verse_notes');
    }
};

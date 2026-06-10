<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bible_verses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('translation', 10);
            $table->tinyInteger('book')->unsigned();     // 1-66
            $table->smallInteger('chapter')->unsigned();
            $table->smallInteger('verse')->unsigned();
            $table->text('text');

            $table->unique(['translation', 'book', 'chapter', 'verse']);
            $table->index(['translation', 'book', 'chapter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_verses');
    }
};

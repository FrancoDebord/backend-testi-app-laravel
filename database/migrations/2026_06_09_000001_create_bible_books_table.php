<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bible_books', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('translation', 10);
            $table->tinyInteger('number')->unsigned(); // 1-66
            $table->string('name', 100);
            $table->string('abbreviation', 10);
            $table->string('testament', 2); // OT ou NT
            $table->smallInteger('chapters_count')->unsigned()->default(0);

            $table->unique(['translation', 'number']);
            $table->index('translation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_books');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('user_id', 36)->index();
            $table->char('testimony_id', 36)->index();
            $table->string('type', 10); // like, love, pray, amen, fire
            $table->timestamps();

            $table->unique(['user_id', 'testimony_id', 'type']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('testimony_id')->references('id')->on('testimonies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};

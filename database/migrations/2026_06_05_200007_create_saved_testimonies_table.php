<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_testimonies', function (Blueprint $table) {
            $table->char('user_id', 36);
            $table->char('testimony_id', 36);
            $table->timestamp('saved_at')->useCurrent();

            $table->primary(['user_id', 'testimony_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('testimony_id')->references('id')->on('testimonies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_testimonies');
    }
};

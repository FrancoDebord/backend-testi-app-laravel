<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('testimony_id', 36)->index();
            $table->char('user_id', 36)->index();
            $table->char('parent_id', 36)->nullable()->index();
            $table->text('body');
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('testimony_id')->references('id')->on('testimonies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('comments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};

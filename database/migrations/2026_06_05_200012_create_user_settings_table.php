<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->char('user_id', 36)->primary();
            $table->boolean('is_private_account')->default(false);
            $table->string('comment_permission', 15)->default('everyone'); // everyone, followers, nobody
            $table->boolean('push_comments')->default(true);
            $table->boolean('push_likes')->default(true);
            $table->boolean('push_prayers')->default(true);
            $table->boolean('push_approval')->default(true);
            $table->boolean('push_new_followed')->default(true);
            $table->string('app_theme', 10)->default('system'); // light, dark, system
            $table->string('language', 5)->default('fr');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};

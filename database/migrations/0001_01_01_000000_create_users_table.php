<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('display_name');
            $table->string('email')->unique()->nullable();
            $table->string('phone', 20)->unique()->nullable();
            $table->string('firebase_uid', 128)->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('country')->nullable();
            $table->text('bio')->nullable();
            $table->string('role', 20)->default('utilisateur'); // visiteur, utilisateur, moderateur, administrateur
            $table->string('status', 20)->default('active'); // active, suspended, banned
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('testimony_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('prayer_count')->default(0);
            $table->unsignedInteger('follower_count')->default(0);
            $table->unsignedInteger('following_count')->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->char('user_id', 36)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

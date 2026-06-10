<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimony_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('testimony_id', 36)->index();
            $table->char('reporter_id', 36)->index();
            $table->string('reason', 40); // inappropriateContent, falseTestimony, hateSpeech, spam, other
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending'); // pending, reviewed, dismissed
            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['testimony_id', 'reporter_id']);
            $table->foreign('testimony_id')->references('id')->on('testimonies')->cascadeOnDelete();
            $table->foreign('reporter_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimony_reports');
    }
};

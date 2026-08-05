<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('content_locale', 16)->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('title');
            $table->json('layout')->nullable();
            $table->json('actions')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('form_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('title')->nullable();
            $table->timestamps();
            $table->unique(['form_id', 'locale']);
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('locale', 16)->nullable();
            $table->string('status', 32)->default('new');
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['form_id', 'status']);
            $table->index(['form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_translations');
        Schema::dropIfExists('forms');
    }
};

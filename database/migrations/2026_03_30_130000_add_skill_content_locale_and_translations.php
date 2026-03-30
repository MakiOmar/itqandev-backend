<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            if (! Schema::hasColumn('skills', 'content_locale')) {
                $table->string('content_locale', 16)->nullable()->after('slug');
            }
        });

        Schema::create('skill_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('name')->nullable();
            $table->string('description', 1024)->nullable();
            $table->timestamps();

            $table->unique(['skill_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_translations');

        Schema::table('skills', function (Blueprint $table) {
            if (Schema::hasColumn('skills', 'content_locale')) {
                $table->dropColumn('content_locale');
            }
        });
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('content_locale', 16)->nullable()->after('project_id');
        });

        Schema::create('testimonial_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('testimonial_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 16);
            $table->text('content')->nullable();
            $table->string('client_role', 255)->nullable();
            $table->string('company', 255)->nullable();
            $table->timestamps();

            $table->unique(['testimonial_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonial_translations');

        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('content_locale');
        });
    }
};

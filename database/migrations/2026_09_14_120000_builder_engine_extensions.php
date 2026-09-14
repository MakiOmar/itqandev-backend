<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_globals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('status', 16)->default('draft');
            $table->json('document');
            $table->timestamps();
        });

        Schema::create('builder_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisable_type', 160);
            $table->unsignedBigInteger('revisable_id');
            $table->json('document');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['revisable_type', 'revisable_id', 'id'], 'builder_revisions_morph_idx');
        });

        Schema::table('theme_templates', function (Blueprint $table) {
            $table->string('document_type', 32)->default('chrome')->after('name');
            $table->index('document_type');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('label');
            $table->unsignedBigInteger('image_id')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['description', 'image_id']);
        });
        Schema::table('theme_templates', function (Blueprint $table) {
            $table->dropIndex(['document_type']);
            $table->dropColumn('document_type');
        });
        Schema::dropIfExists('builder_revisions');
        Schema::dropIfExists('builder_globals');
    }
};

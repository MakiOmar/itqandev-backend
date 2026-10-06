<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('subtitle', 255)->nullable()->after('title');
        });
        Schema::table('page_translations', function (Blueprint $table) {
            $table->string('subtitle', 255)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('page_translations', function (Blueprint $table) {
            $table->dropColumn('subtitle');
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('subtitle');
        });
    }
};

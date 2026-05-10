<?php

use App\Support\SiteLanguages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $default = SiteLanguages::defaultCode();

        Schema::table('seo_metas', function (Blueprint $table) use ($default) {
            $table->string('locale', 16)->default($default)->after('seoable_id');
        });

        Schema::table('seo_metas', function (Blueprint $table) {
            $table->unique(['seoable_type', 'seoable_id', 'locale'], 'seo_metas_seoable_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::table('seo_metas', function (Blueprint $table) {
            $table->dropUnique('seo_metas_seoable_locale_unique');
            $table->dropColumn('locale');
        });
    }
};

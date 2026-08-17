<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('pages')
                ->nullOnDelete();
            $table->boolean('exclude_from_search')
                ->default(false)
                ->after('status');
            $table->index(['parent_id', 'status']);
        });

        $version = (int) Cache::get('pages:cache_version', 1);
        Cache::forever('pages:cache_version', $version + 1);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'status']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('exclude_from_search');
        });
    }
};

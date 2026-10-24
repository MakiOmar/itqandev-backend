<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The API already accepts posts without body copy (drafts, title-only posts).
     */
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->longText('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('blog_posts')->whereNull('content')->update(['content' => '']);

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->longText('content')->nullable(false)->change();
        });
    }
};

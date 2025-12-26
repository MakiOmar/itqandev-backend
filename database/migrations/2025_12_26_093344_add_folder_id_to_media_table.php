<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if columns don't exist before adding
        if (!Schema::hasColumn('media', 'folder_id')) {
            Schema::table('media', function (Blueprint $table) {
                $table->unsignedBigInteger('folder_id')->nullable()->after('model_id');
            });
        }

        if (!Schema::hasColumn('media', 'alt_text')) {
            Schema::table('media', function (Blueprint $table) {
                $table->string('alt_text')->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn('media', 'description')) {
            Schema::table('media', function (Blueprint $table) {
                $table->text('description')->nullable()->after('alt_text');
            });
        }

        // Add foreign key with check
        if (Schema::hasColumn('media', 'folder_id') && Schema::hasTable('media_folders')) {
            try {
                Schema::table('media', function (Blueprint $table) {
                    $table->foreign('folder_id')->references('id')->on('media_folders')->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Foreign key might already exist, ignore
            }

            try {
                Schema::table('media', function (Blueprint $table) {
                    $table->index('folder_id');
                });
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            if (Schema::hasColumn('media', 'folder_id')) {
                try {
                    $table->dropForeign(['folder_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                try {
                    $table->dropIndex(['folder_id']);
                } catch (\Exception $e) {
                    // Index might not exist
                }
            }
            if (Schema::hasColumn('media', 'folder_id')) {
                $table->dropColumn('folder_id');
            }
            if (Schema::hasColumn('media', 'alt_text')) {
                $table->dropColumn('alt_text');
            }
            if (Schema::hasColumn('media', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};

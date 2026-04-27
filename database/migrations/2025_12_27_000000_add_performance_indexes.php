<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for media and pivot tables.
 *
 * Must run after create_media_table and related media migrations (filename order).
 * Previously lived as 2025_01_27_* which ran before December 2025 migrations and failed on fresh DBs.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add composite index for media queries by model_type and model_id
        Schema::table('media', function (Blueprint $table) {
            if (! $this->indexExists('media', 'media_model_type_model_id_index')) {
                $table->index(['model_type', 'model_id'], 'media_model_type_model_id_index');
            }
        });

        // Add index for media collection_name (frequently queried)
        Schema::table('media', function (Blueprint $table) {
            if (! $this->indexExists('media', 'media_collection_name_index')) {
                $table->index('collection_name', 'media_collection_name_index');
            }
        });

        // Add index for media folder_id
        Schema::table('media', function (Blueprint $table) {
            if (! $this->indexExists('media', 'media_folder_id_index')) {
                $table->index('folder_id', 'media_folder_id_index');
            }
        });

        // Verify indexes on pivot tables (they should already have primary keys, but ensure indexes exist)
        Schema::table('category_project', function (Blueprint $table) {
            // Primary key already exists, but add individual indexes if needed
            if (! $this->indexExists('category_project', 'category_project_category_id_index')) {
                $table->index('category_id', 'category_project_category_id_index');
            }
            if (! $this->indexExists('category_project', 'category_project_project_id_index')) {
                $table->index('project_id', 'category_project_project_id_index');
            }
        });

        Schema::table('project_skill', function (Blueprint $table) {
            // Primary key already exists, but add individual indexes if needed
            if (! $this->indexExists('project_skill', 'project_skill_project_id_index')) {
                $table->index('project_id', 'project_skill_project_id_index');
            }
            if (! $this->indexExists('project_skill', 'project_skill_skill_id_index')) {
                $table->index('skill_id', 'project_skill_skill_id_index');
            }
        });

        // Add index for testimonials project_id (foreign key)
        Schema::table('testimonials', function (Blueprint $table) {
            if (! $this->indexExists('testimonials', 'testimonials_project_id_index')) {
                $table->index('project_id', 'testimonials_project_id_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('media_model_type_model_id_index');
            $table->dropIndex('media_collection_name_index');
            $table->dropIndex('media_folder_id_index');
        });

        Schema::table('category_project', function (Blueprint $table) {
            $table->dropIndex('category_project_category_id_index');
            $table->dropIndex('category_project_project_id_index');
        });

        Schema::table('project_skill', function (Blueprint $table) {
            $table->dropIndex('project_skill_project_id_index');
            $table->dropIndex('project_skill_skill_id_index');
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropIndex('testimonials_project_id_index');
        });
    }

    /**
     * Check if an index exists on a table.
     */
    protected function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $rows = $connection->select(
                'SELECT name FROM sqlite_master WHERE type = ? AND tbl_name = ? AND name = ?',
                ['index', $table, $index]
            );

            return count($rows) > 0;
        }

        $databaseName = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$databaseName, $table, $index]
        );

        return $result[0]->count > 0;
    }
};

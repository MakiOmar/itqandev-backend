<?php

use App\Services\Appearance\ChromeLayoutSupport;
use App\Services\Appearance\FooterBuilderService;
use App\Services\Appearance\HeaderBuilderService;
use App\Support\ProjectSettingsStore;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chrome_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->string('name');
            $table->string('slug');
            $table->string('status', 16)->default('draft');
            $table->json('document');
            $table->boolean('is_site_default')->default(false);
            $table->timestamps();

            $table->unique(['kind', 'slug']);
            $table->index(['kind', 'is_site_default']);
            $table->index(['kind', 'status']);
        });

        foreach (['pages', 'projects', 'blog_posts', 'services'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('header_layout_id')
                    ->nullable()
                    ->constrained('chrome_layouts')
                    ->nullOnDelete();
                $table->foreignId('footer_layout_id')
                    ->nullable()
                    ->constrained('chrome_layouts')
                    ->nullOnDelete();
            });
        }

        $this->seedDefaultsFromSettings();
    }

    public function down(): void
    {
        foreach (['pages', 'projects', 'blog_posts', 'services'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'header_layout_id')) {
                    $table->dropConstrainedForeignId('header_layout_id');
                }
                if (Schema::hasColumn($tableName, 'footer_layout_id')) {
                    $table->dropConstrainedForeignId('footer_layout_id');
                }
            });
        }

        Schema::dropIfExists('chrome_layouts');
    }

    private function seedDefaultsFromSettings(): void
    {
        $stored = [];
        try {
            $stored = ProjectSettingsStore::load();
        } catch (\Throwable) {
            $stored = [];
        }

        $headerService = new HeaderBuilderService;
        $footerService = new FooterBuilderService;

        $headerRaw = is_array($stored[HeaderBuilderService::SETTINGS_KEY] ?? null)
            ? $stored[HeaderBuilderService::SETTINGS_KEY]
            : null;
        $footerRaw = is_array($stored[FooterBuilderService::SETTINGS_KEY] ?? null)
            ? $stored[FooterBuilderService::SETTINGS_KEY]
            : null;

        $headerDoc = $headerService->defaultDocument();
        if (is_array($headerRaw) && isset($headerRaw['sections']) && is_array($headerRaw['sections']) && $headerRaw['sections'] !== []) {
            $headerDoc = ChromeLayoutSupport::normalizeDocument($headerRaw);
        }

        $footerDoc = $footerService->defaultDocument();
        if (is_array($footerRaw)) {
            if (isset($footerRaw['zones']) && is_array($footerRaw['zones']) && (! isset($footerRaw['sections']) || $footerRaw['sections'] === [])) {
                $migrated = ChromeLayoutSupport::migrateLegacyFooterZones($footerRaw);
                if ($migrated !== null) {
                    $footerDoc = $migrated;
                }
            } elseif (isset($footerRaw['sections']) && is_array($footerRaw['sections']) && $footerRaw['sections'] !== []) {
                $footerDoc = ChromeLayoutSupport::normalizeDocument($footerRaw);
            }
        }

        $now = now();
        DB::table('chrome_layouts')->insert([
            [
                'kind' => 'header',
                'name' => 'Default header',
                'slug' => 'default-header',
                'status' => 'published',
                'document' => json_encode($headerDoc, JSON_UNESCAPED_UNICODE),
                'is_site_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'kind' => 'footer',
                'name' => 'Default footer',
                'slug' => 'default-footer',
                'status' => 'published',
                'document' => json_encode($footerDoc, JSON_UNESCAPED_UNICODE),
                'is_site_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
};

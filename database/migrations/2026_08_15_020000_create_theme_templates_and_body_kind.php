<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // chrome_layouts.kind is already a string(16); "body" fits without altering the column.

        Schema::create('theme_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 16)->default('draft');
            $table->json('conditions');
            $table->foreignId('header_layout_id')
                ->nullable()
                ->constrained('chrome_layouts')
                ->nullOnDelete();
            $table->foreignId('footer_layout_id')
                ->nullable()
                ->constrained('chrome_layouts')
                ->nullOnDelete();
            $table->foreignId('body_layout_id')
                ->nullable()
                ->constrained('chrome_layouts')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_templates');
    }
};

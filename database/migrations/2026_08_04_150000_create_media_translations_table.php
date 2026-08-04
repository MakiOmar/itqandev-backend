<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('media_translations')) {
            return;
        }

        Schema::create('media_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_id');
            $table->string('locale', 16);
            $table->string('alt_text')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['media_id', 'locale']);
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_translations');
    }
};

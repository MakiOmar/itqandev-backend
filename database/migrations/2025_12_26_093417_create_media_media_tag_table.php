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
        if (Schema::hasTable('media_media_tag')) {
            return;
        }

        Schema::create('media_media_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_id');
            $table->unsignedBigInteger('media_tag_id');
            $table->timestamps();

            $table->foreign('media_id')->references('id')->on('media')->onDelete('cascade');
            $table->foreign('media_tag_id')->references('id')->on('media_tags')->onDelete('cascade');
            $table->unique(['media_id', 'media_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_media_tag');
    }
};

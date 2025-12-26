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
        if (Schema::hasTable('media_usages')) {
            return;
        }

        Schema::create('media_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_id');
            $table->morphs('usable'); // polymorphic relation to track where media is used (automatically creates index)
            $table->string('collection_name')->nullable();
            $table->timestamps();

            $table->foreign('media_id')->references('id')->on('media')->onDelete('cascade');
            $table->index('media_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_usages');
    }
};

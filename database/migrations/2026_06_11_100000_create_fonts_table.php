<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fonts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('css_family', 120);
            $table->string('file_woff2', 2048)->nullable();
            $table->string('file_woff', 2048)->nullable();
            $table->string('file_ttf', 2048)->nullable();
            $table->string('file_eot', 2048)->nullable();
            $table->string('file_svg', 2048)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fonts');
    }
};

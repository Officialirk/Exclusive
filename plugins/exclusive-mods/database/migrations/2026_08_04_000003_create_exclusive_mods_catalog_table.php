<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exclusive_mods_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('driver');
            $table->string('workshop_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('preview_image_url')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['driver', 'workshop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exclusive_mods_catalog');
    }
};

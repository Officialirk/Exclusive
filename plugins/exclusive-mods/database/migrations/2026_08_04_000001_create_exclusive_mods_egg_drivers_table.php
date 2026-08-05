<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exclusive_mods_egg_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('egg_id')->constrained('eggs')->cascadeOnDelete();
            $table->string('driver');
            $table->timestamps();

            $table->unique('egg_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exclusive_mods_egg_drivers');
    }
};

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
            // eggs.id is a plain unsignedInteger (created via increments()), not
            // unsignedBigInteger - foreignId() would create a column type
            // mismatch that fails the FK constraint on MySQL. Match core's own
            // convention (see e.g. servers.id references in the backups table).
            $table->unsignedInteger('egg_id');
            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
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

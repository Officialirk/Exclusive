<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exclusive_mods_server_mods', function (Blueprint $table) {
            $table->id();
            // servers.id is a plain unsignedInteger (increments()), not
            // unsignedBigInteger - foreignId() would mismatch the column type
            // and fail the FK constraint on MySQL. exclusive_mods_catalog.id
            // (below) is our own table created with $table->id(), so
            // foreignId() is correct there.
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreignId('mod_id')->constrained('exclusive_mods_catalog')->cascadeOnDelete();
            $table->unsignedInteger('load_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->string('installed_version')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['server_id', 'mod_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exclusive_mods_server_mods');
    }
};

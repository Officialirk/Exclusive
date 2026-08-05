<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exclusive_mods_server_query_configs', function (Blueprint $table) {
            $table->id();
            // servers.id is a plain unsignedInteger (created via increments()),
            // not unsignedBigInteger - foreignId() would mismatch the column
            // type and fail the FK constraint on MySQL (mirrors the pattern in
            // core's own backups table migration).
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->unsignedSmallInteger('rest_api_port')->nullable();
            $table->text('rest_api_password')->nullable();
            $table->timestamps();

            $table->unique('server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exclusive_mods_server_query_configs');
    }
};

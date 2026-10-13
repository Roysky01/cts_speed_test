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
        Schema::create('speed_tests', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('user_agent', 512)->nullable();
            $table->decimal('ping_ms', 8, 2);
            $table->decimal('jitter_ms', 8, 2);
            $table->decimal('loaded_ping_ms', 8, 2)->nullable();
            $table->decimal('download_mbps', 8, 2);
            $table->decimal('upload_mbps', 8, 2);
            $table->unsignedBigInteger('download_bytes');
            $table->unsignedBigInteger('upload_bytes');
            $table->unsignedInteger('download_ms');
            $table->unsignedInteger('upload_ms');
            $table->timestamps();

            $table->index(['ip_address', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('speed_tests');
    }
};

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
        Schema::create('webtv_playlists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['live', 'scheduled', 'loop'])->default('live');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_loop')->default(false);
            $table->boolean('is_auto_start')->default(false);
            $table->string('ant_media_stream_id')->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'error'])->default('pending');
            $table->timestamp('last_sync_at')->nullable();
            $table->integer('total_duration')->default(0);
            $table->integer('total_items')->default(0);
            $table->enum('quality', ['720p', '1080p', '4K'])->default('1080p');
            $table->integer('bitrate')->default(2500);
            $table->integer('buffer_duration')->default(5);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webtv_playlists');
    }
};

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
        Schema::create('webtv_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webtv_playlist_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('video_file_id')->nullable();
            $table->string('stream_url', 500)->nullable();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->integer('order')->default(0);
            $table->integer('duration')->default(0);
            $table->enum('quality', ['720p', '1080p', '4K'])->default('1080p');
            $table->integer('bitrate')->default(2500);
            $table->string('ant_media_item_id')->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'error'])->default('pending');
            $table->boolean('is_live_stream')->default(false);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webtv_playlist_items');
    }
};

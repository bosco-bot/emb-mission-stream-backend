<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("webtv_streams", function (Blueprint $table) {
            $table->id();
            $table->string("ant_media_stream_id")->unique()->nullable();
            $table->string("title");
            $table->text("description")->nullable();
            $table->enum("status", ["live", "offline", "scheduled", "finished"])->default("offline");
            $table->timestamp("start_time")->nullable();
            $table->timestamp("end_time")->nullable();
            $table->string("playback_url")->nullable();
            $table->string("webrtc_url")->nullable();
            $table->string("thumbnail_url")->nullable();
            $table->text("embed_code")->nullable();
            $table->boolean("is_featured")->default(false);
            $table->json("metadata")->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(["status", "is_featured"]);
            $table->index("created_at");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("webtv_streams");
    }
};

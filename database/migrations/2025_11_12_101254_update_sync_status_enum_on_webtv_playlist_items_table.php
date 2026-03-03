<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `webtv_playlist_items` MODIFY COLUMN `sync_status` ENUM('pending','processing','synced','error') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `webtv_playlist_items` MODIFY COLUMN `sync_status` ENUM('pending','synced','error') DEFAULT 'pending'");
    }
};

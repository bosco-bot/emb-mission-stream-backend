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
        Schema::table('webtv_playlist_items', function (Blueprint $table) {
            $table->string('unique_id')->nullable()->after('id');
            $table->index('unique_id'); // Index pour les recherches rapides
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webtv_playlist_items', function (Blueprint $table) {
            $table->dropIndex(['unique_id']);
            $table->dropColumn('unique_id');
        });
    }
};







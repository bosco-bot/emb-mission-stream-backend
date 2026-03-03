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
        Schema::create('playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->onDelete('cascade');
            $table->foreignId('media_file_id')->constrained()->onDelete('cascade');
            
            // Ordre dans la playlist
            $table->integer('order')->default(0);
            
            // Synchronisation avec AzuraCast
            $table->integer('azuracast_song_id')->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'error'])->default('pending');
            
            // Métadonnées
            $table->integer('duration')->nullable(); // durée du fichier en secondes
            
            $table->timestamps();
            
            // Index pour les performances
            $table->index(['playlist_id', 'order']);
            $table->index('azuracast_song_id');
            
            // Contrainte unique pour éviter les doublons
            $table->unique(['playlist_id', 'media_file_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlist_items');
    }
};

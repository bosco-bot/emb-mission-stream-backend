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
        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('default'); // default, morning, evening, etc.
            
            // Options de lecture
            $table->boolean('is_loop')->default(false);
            $table->boolean('is_shuffle')->default(false);
            
            // Synchronisation avec AzuraCast
            $table->integer('azuracast_id')->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'error'])->default('pending');
            $table->timestamp('last_sync_at')->nullable();
            
            // Métadonnées
            $table->integer('total_duration')->default(0); // en secondes
            $table->integer('total_items')->default(0);
            
            $table->timestamps();
            
            // Index pour les recherches
            $table->index(['type', 'sync_status']);
            $table->index('azuracast_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlists');
    }
};

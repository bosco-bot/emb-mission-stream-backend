<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_file_relations', function (Blueprint $table) {
            $table->id();
            
            // Fichiers liés (seulement pour AUDIO)
            $table->unsignedBigInteger('audio_file_id')->nullable()->comment('ID du fichier audio (NULL pour vidéos)');
            $table->unsignedBigInteger('video_file_id')->nullable()->comment('ID du fichier vidéo (NULL pour audio)');
            $table->unsignedBigInteger('thumbnail_file_id')->nullable()->comment('ID du fichier thumbnail');
            
            // Type de relation
            $table->enum('relation_type', [
                'audio_thumbnail_manual',     // Audio + thumbnail manuel
                'audio_embedded_artwork',     // Audio avec artwork embarqué
                'video_auto_thumbnail',       // Vidéo avec thumbnail auto-généré
                'image_standalone'            // Image standalone
            ])->default('audio_thumbnail_manual')->comment('Comment la relation a été établie');
            
            // Confiance dans la correspondance
            $table->decimal('confidence_score', 3, 2)->nullable()->comment('Score de confiance (0.00 à 1.00)');
            $table->string('match_method', 50)->nullable()->comment('Méthode de correspondance utilisée');
            
            // Métadonnées de la relation
            $table->boolean('is_primary')->default(true)->comment('Relation principale (un audio peut avoir plusieurs thumbnails)');
            $table->boolean('is_active')->default(true)->comment('Relation active');
            
            $table->timestamps();
            
            // Clés étrangères
            $table->foreign('audio_file_id')->references('id')->on('media_files')->onDelete('cascade');
            $table->foreign('video_file_id')->references('id')->on('media_files')->onDelete('cascade');
            $table->foreign('thumbnail_file_id')->references('id')->on('media_files')->onDelete('cascade');
            
            // Index et contraintes
            $table->unique(['audio_file_id', 'thumbnail_file_id'], 'unique_audio_thumbnail');
            $table->index('audio_file_id');
            $table->index('video_file_id');
            $table->index('thumbnail_file_id');
            $table->index('relation_type');
            $table->index('confidence_score');
        });
        
        // Ajouter la contrainte CHECK après création de la table
        DB::statement('ALTER TABLE media_file_relations ADD CONSTRAINT chk_audio_or_video CHECK ((audio_file_id IS NOT NULL AND video_file_id IS NULL) OR (audio_file_id IS NULL AND video_file_id IS NOT NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_file_relations');
    }
};

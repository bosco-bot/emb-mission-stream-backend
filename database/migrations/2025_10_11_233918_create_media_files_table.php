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
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            
            // Informations du fichier
            $table->string('filename')->comment('Nom du fichier stocké');
            $table->string('original_name')->comment('Nom original du fichier');
            $table->string('file_path')->comment('Chemin complet vers le fichier');
            $table->string('file_url')->nullable()->comment('URL publique d\'accès au fichier');
            
            // Métadonnées du fichier
            $table->enum('file_type', ['video', 'audio', 'image'])->comment('Type de fichier');
            $table->string('mime_type')->comment('Type MIME du fichier');
            $table->bigInteger('file_size')->unsigned()->comment('Taille en octets');
            $table->string('file_size_formatted')->nullable()->comment('Taille formatée (ex: 5.2 MB)');
            
            // Métadonnées spécifiques
            $table->integer('duration')->nullable()->comment('Durée en secondes (vidéo/audio)');
            $table->integer('width')->nullable()->comment('Largeur en pixels (vidéo/image)');
            $table->integer('height')->nullable()->comment('Hauteur en pixels (vidéo/image)');
            $table->integer('bitrate')->nullable()->comment('Débit en kbps (audio/vidéo)');
            $table->integer('sample_rate')->nullable()->comment('Fréquence d\'échantillonnage (audio)');
            $table->tinyInteger('channels')->nullable()->comment('Nombre de canaux (audio)');
            
            // Statut d'importation
            $table->enum('status', ['uploading', 'importing', 'processing', 'completed', 'error'])
                  ->default('uploading')->comment('Statut du fichier');
            $table->tinyInteger('progress')->unsigned()->default(0)->comment('Progression en pourcentage (0-100)');
            $table->text('error_message')->nullable()->comment('Message d\'erreur si échec');
            
            // Informations d'importation
            $table->bigInteger('bytes_uploaded')->unsigned()->default(0)->comment('Octets uploadés');
            $table->bigInteger('bytes_total')->unsigned()->default(0)->comment('Total d\'octets à uploader');
            $table->string('estimated_time_remaining', 50)->nullable()->comment('Temps restant estimé');
            
            // Thumbnails et artworks
            $table->string('thumbnail_path')->nullable()->comment('Chemin vers le thumbnail généré');
            $table->string('thumbnail_url')->nullable()->comment('URL publique du thumbnail');
            $table->boolean('has_embedded_artwork')->default(false)->comment('Contient un artwork embarqué');
            
            // Métadonnées additionnelles
            $table->json('metadata')->nullable()->comment('Métadonnées extraites du fichier');
            
            $table->timestamps();
            
            // Index
            $table->index('file_type');
            $table->index('status');
            $table->index('created_at');
            $table->index('filename');
            $table->index('original_name');
            $table->index(['file_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};

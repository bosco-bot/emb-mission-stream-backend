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
        Schema::create('upload_session_files', function (Blueprint $table) {
            $table->id();
            
            // Liaisons
            $table->unsignedBigInteger('session_id')->comment('ID de la session d\'upload');
            $table->unsignedBigInteger('media_file_id')->comment('ID du fichier média');
            
            // Ordre et groupement
            $table->integer('upload_order')->unsigned()->comment('Ordre d\'upload dans la session');
            $table->string('file_group', 100)->nullable()->comment('Groupe de fichiers (ex: album_name)');
            
            // Correspondances détectées
            $table->unsignedBigInteger('suggested_thumbnail_id')->nullable()->comment('ID du thumbnail suggéré');
            $table->decimal('match_confidence', 3, 2)->nullable()->comment('Confiance dans la correspondance');
            $table->string('match_method', 50)->nullable()->comment('Méthode de correspondance');
            
            // Statut spécifique à la session
            $table->enum('session_status', ['pending', 'uploading', 'completed', 'error', 'cancelled'])
                  ->default('pending')->comment('Statut dans cette session');
            $table->text('error_message')->nullable()->comment('Message d\'erreur spécifique à la session');
            
            $table->timestamps();
            
            // Clés étrangères
            $table->foreign('session_id')->references('id')->on('upload_sessions')->onDelete('cascade');
            $table->foreign('media_file_id')->references('id')->on('media_files')->onDelete('cascade');
            $table->foreign('suggested_thumbnail_id')->references('id')->on('media_files')->onDelete('set null');
            
            // Index et contraintes
            $table->unique(['session_id', 'media_file_id'], 'unique_session_file');
            $table->index('session_id');
            $table->index('media_file_id');
            $table->index('upload_order');
            $table->index('session_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_session_files');
    }
};

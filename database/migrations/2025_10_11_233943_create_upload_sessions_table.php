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
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->id();
            
            // Identifiant de session
            $table->string('session_token', 64)->unique()->comment('Token unique de la session');
            $table->unsignedBigInteger('user_id')->nullable()->comment('ID de l\'utilisateur (si authentifié)');
            
            // Informations de la session
            $table->integer('total_files')->unsigned()->default(0)->comment('Nombre total de fichiers dans la session');
            $table->integer('uploaded_files')->unsigned()->default(0)->comment('Nombre de fichiers uploadés');
            $table->integer('completed_files')->unsigned()->default(0)->comment('Nombre de fichiers traités avec succès');
            $table->integer('failed_files')->unsigned()->default(0)->comment('Nombre de fichiers en échec');
            
            // Statut global
            $table->enum('status', ['active', 'processing', 'completed', 'failed', 'cancelled'])
                  ->default('active')->comment('Statut global de la session');
            $table->tinyInteger('progress')->unsigned()->default(0)->comment('Progression globale en pourcentage');
            
            // Métadonnées
            $table->bigInteger('total_size')->unsigned()->default(0)->comment('Taille totale des fichiers en octets');
            $table->bigInteger('uploaded_size')->unsigned()->default(0)->comment('Taille uploadée en octets');
            $table->string('estimated_time_remaining', 50)->nullable()->comment('Temps restant estimé');
            
            // Configuration
            $table->boolean('auto_match_thumbnails')->default(true)->comment('Correspondance automatique des thumbnails');
            $table->boolean('generate_missing_thumbnails')->default(true)->comment('Générer les thumbnails manquants');
            
            // Timestamps
            $table->timestamp('started_at')->useCurrent()->comment('Début de la session');
            $table->timestamp('completed_at')->nullable()->comment('Fin de la session');
            $table->timestamp('expires_at')->nullable()->comment('Expiration de la session');
            $table->timestamps();
            
            // Index
            $table->index('session_token');
            $table->index('user_id');
            $table->index('status');
            $table->index('started_at');
            $table->index('expires_at');
            $table->index(['status', 'progress']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
    }
};

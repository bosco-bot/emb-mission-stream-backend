<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Table pour les statistiques WebTV
        Schema::create('web_tv_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('live_audience')->default(0)->comment('Nombre de viewers en direct');
            $table->integer('total_views')->default(0)->comment('Total des vues sur la journée');
            $table->integer('broadcast_duration_seconds')->default(0)->comment('Durée de diffusion en secondes');
            $table->integer('engagement')->default(0)->comment("Score d'engagement");
            $table->timestamps();
        });

        // Table pour les statistiques WebRadio
        Schema::create('web_radio_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('live_audience')->default(0)->comment("Nombre d'auditeurs en direct");
            $table->integer('total_listens')->default(0)->comment('Total des écoutes sur la journée');
            $table->integer('broadcast_duration_seconds')->default(0)->comment('Durée de diffusion en secondes');
            $table->integer('engagement')->default(0)->comment("Score d'engagement");
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('web_tv_stats');
        Schema::dropIfExists('web_radio_stats');
    }
};

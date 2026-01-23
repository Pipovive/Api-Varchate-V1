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
        Schema::table('respuestas_evaluacion', function (Blueprint $table) {
            $table->foreign(['intento_id'], 'respuestas_evaluacion_ibfk_1')->references(['id'])->on('intentos_evaluacion')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['pregunta_evaluacion_id'], 'respuestas_evaluacion_ibfk_2')->references(['id'])->on('preguntas_evaluacion')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['opcion_seleccionada_id'], 'respuestas_evaluacion_ibfk_3')->references(['id'])->on('opciones_evaluacion')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('respuestas_evaluacion', function (Blueprint $table) {
            $table->dropForeign('respuestas_evaluacion_ibfk_1');
            $table->dropForeign('respuestas_evaluacion_ibfk_2');
            $table->dropForeign('respuestas_evaluacion_ibfk_3');
        });
    }
};

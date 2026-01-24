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
        Schema::table('opciones_evaluacion', function (Blueprint $table) {
            $table->foreign(['pregunta_evaluacion_id'], 'opciones_evaluacion_ibfk_1')->references(['id'])->on('preguntas_evaluacion')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opciones_evaluacion', function (Blueprint $table) {
            $table->dropForeign('opciones_evaluacion_ibfk_1');
        });
    }
};

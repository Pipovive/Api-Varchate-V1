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
        Schema::table('preguntas_evaluacion', function (Blueprint $table) {
            $table->foreign(['evaluacion_id'], 'preguntas_evaluacion_ibfk_1')->references(['id'])->on('evaluaciones')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['created_by'], 'preguntas_evaluacion_ibfk_2')->references(['id'])->on('usuarios')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('preguntas_evaluacion', function (Blueprint $table) {
            $table->dropForeign('preguntas_evaluacion_ibfk_1');
            $table->dropForeign('preguntas_evaluacion_ibfk_2');
        });
    }
};

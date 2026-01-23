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
        Schema::table('intentos_evaluacion', function (Blueprint $table) {
            $table->foreign(['usuario_id'], 'intentos_evaluacion_ibfk_1')->references(['id'])->on('usuarios')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['evaluacion_id'], 'intentos_evaluacion_ibfk_2')->references(['id'])->on('evaluaciones')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intentos_evaluacion', function (Blueprint $table) {
            $table->dropForeign('intentos_evaluacion_ibfk_1');
            $table->dropForeign('intentos_evaluacion_ibfk_2');
        });
    }
};

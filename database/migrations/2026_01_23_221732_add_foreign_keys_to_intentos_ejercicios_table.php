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
        Schema::table('intentos_ejercicios', function (Blueprint $table) {
            $table->foreign(['usuario_id'], 'intentos_ejercicios_ibfk_1')->references(['id'])->on('usuarios')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['ejercicio_id'], 'intentos_ejercicios_ibfk_2')->references(['id'])->on('ejercicios')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['opcion_seleccionada_id'], 'intentos_ejercicios_ibfk_3')->references(['id'])->on('opciones_ejercicio')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intentos_ejercicios', function (Blueprint $table) {
            $table->dropForeign('intentos_ejercicios_ibfk_1');
            $table->dropForeign('intentos_ejercicios_ibfk_2');
            $table->dropForeign('intentos_ejercicios_ibfk_3');
        });
    }
};

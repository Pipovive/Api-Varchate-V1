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
        Schema::table('certificaciones', function (Blueprint $table) {
            $table->foreign(['usuario_id'], 'certificaciones_ibfk_1')->references(['id'])->on('usuarios')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['modulo_id'], 'certificaciones_ibfk_2')->references(['id'])->on('modulos')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['intento_evaluacion_id'], 'certificaciones_ibfk_3')->references(['id'])->on('intentos_evaluacion')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificaciones', function (Blueprint $table) {
            $table->dropForeign('certificaciones_ibfk_1');
            $table->dropForeign('certificaciones_ibfk_2');
            $table->dropForeign('certificaciones_ibfk_3');
        });
    }
};

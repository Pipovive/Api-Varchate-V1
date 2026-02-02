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
        Schema::create('ranking', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('modulo_id');
            $table->unsignedBigInteger('usuario_id')->index('usuario_id');
            $table->decimal('porcentaje_progreso', 5)->nullable()->default(0);
            $table->integer('posicion')->nullable();
            $table->dateTime('fecha_ultima_actualizacion')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['modulo_id', 'porcentaje_progreso', 'fecha_ultima_actualizacion'], 'idx_ranking_modulo');
            $table->unique(['modulo_id', 'usuario_id'], 'unique_modulo_usuario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ranking');
    }
};

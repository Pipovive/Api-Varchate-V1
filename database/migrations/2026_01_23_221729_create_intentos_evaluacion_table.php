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
        Schema::create('intentos_evaluacion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('evaluacion_id')->index('evaluacion_id');
            $table->integer('intento_numero');
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->integer('tiempo_utilizado')->nullable()->comment('Segundos');
            $table->decimal('puntuacion_total', 5)->nullable()->default(0);
            $table->decimal('porcentaje_obtenido', 5)->nullable()->default(0);
            $table->integer('preguntas_correctas')->nullable()->default(0);
            $table->integer('preguntas_incorrectas')->nullable()->default(0);
            $table->boolean('aprobado')->nullable()->default(false)->index('idx_aprobado');
            $table->enum('estado', ['en_progreso', 'completado', 'expirado'])->nullable()->default('en_progreso');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->index(['usuario_id', 'evaluacion_id'], 'idx_usuario_evaluacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intentos_evaluacion');
    }
};

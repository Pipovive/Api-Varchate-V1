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
        Schema::create('progreso_modulo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id')->index('idx_usuario');
            $table->unsignedBigInteger('modulo_id');
            $table->decimal('porcentaje_completado', 5)->nullable()->default(0);
            $table->integer('lecciones_vistas')->nullable()->default(0);
            $table->integer('total_lecciones')->nullable()->default(0);
            $table->boolean('evaluacion_aprobada')->nullable()->default(false);
            $table->boolean('certificado_disponible')->nullable()->default(false);
            $table->dateTime('fecha_ultimo_progreso')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->index(['modulo_id', 'porcentaje_completado'], 'idx_progreso');
            $table->unique(['usuario_id', 'modulo_id'], 'unique_usuario_modulo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progreso_modulo');
    }
};

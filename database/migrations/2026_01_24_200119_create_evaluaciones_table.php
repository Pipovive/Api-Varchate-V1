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
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('modulo_id')->index('idx_modulo');
            $table->string('titulo')->default('Evaluación Final');
            $table->text('descripcion')->nullable();
            $table->integer('numero_preguntas')->default(10);
            $table->integer('tiempo_limite')->comment('Minutos');
            $table->decimal('puntaje_minimo', 5)->default(70);
            $table->integer('max_intentos')->nullable()->default(3);
            $table->enum('estado', ['activo', 'inactivo'])->nullable()->default('activo');
            $table->unsignedBigInteger('created_by')->index('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->unique(['modulo_id'], 'unique_modulo_evaluacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};

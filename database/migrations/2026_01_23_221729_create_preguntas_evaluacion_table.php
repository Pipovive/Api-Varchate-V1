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
        Schema::create('preguntas_evaluacion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('evaluacion_id');
            $table->text('pregunta');
            $table->enum('tipo', ['seleccion_multiple', 'verdadero_falso', 'arrastrar_soltar']);
            $table->decimal('puntos', 5)->nullable()->default(1);
            $table->integer('orden')->nullable()->default(0);
            $table->unsignedBigInteger('created_by')->index('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->index(['evaluacion_id', 'orden'], 'idx_evaluacion_orden');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preguntas_evaluacion');
    }
};

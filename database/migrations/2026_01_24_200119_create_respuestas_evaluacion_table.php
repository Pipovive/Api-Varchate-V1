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
        Schema::create('respuestas_evaluacion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('intento_id')->index('idx_intento');
            $table->unsignedBigInteger('pregunta_evaluacion_id')->index('pregunta_evaluacion_id');
            $table->unsignedBigInteger('opcion_seleccionada_id')->nullable()->index('opcion_seleccionada_id');
            $table->text('respuesta_texto')->nullable();
            $table->boolean('es_correcta')->nullable()->default(false);
            $table->decimal('puntos_obtenidos', 5)->nullable()->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('respuestas_evaluacion');
    }
};

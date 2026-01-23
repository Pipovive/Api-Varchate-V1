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
        Schema::create('intentos_ejercicios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('ejercicio_id')->index('ejercicio_id');
            $table->unsignedBigInteger('opcion_seleccionada_id')->nullable()->index('opcion_seleccionada_id');
            $table->text('respuesta_texto')->nullable();
            $table->boolean('es_correcta')->nullable()->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['usuario_id', 'ejercicio_id'], 'idx_usuario_ejercicio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intentos_ejercicios');
    }
};

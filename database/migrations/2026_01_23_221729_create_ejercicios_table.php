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
        Schema::create('ejercicios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('leccion_id');
            $table->text('pregunta');
            $table->enum('tipo', ['seleccion_multiple', 'verdadero_falso', 'arrastrar_soltar']);
            $table->integer('orden')->nullable()->default(0);
            $table->enum('estado', ['activo', 'inactivo'])->nullable()->default('activo');
            $table->unsignedBigInteger('created_by')->index('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->index(['leccion_id', 'orden'], 'idx_leccion_orden');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ejercicios');
    }
};

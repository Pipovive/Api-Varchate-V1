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
        Schema::create('certificaciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id')->index('idx_usuario');
            $table->unsignedBigInteger('modulo_id')->index('modulo_id');
            $table->unsignedBigInteger('intento_evaluacion_id')->index('intento_evaluacion_id');
            $table->string('codigo_certificado', 20)->unique('codigo_certificado');
            $table->decimal('porcentaje_obtenido', 5);
            $table->dateTime('fecha_emision');
            $table->dateTime('fecha_descarga')->nullable();
            $table->boolean('descargado')->nullable()->default(false);
            $table->string('hash_verificacion', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->index(['codigo_certificado'], 'idx_codigo');
            $table->unique(['usuario_id', 'modulo_id'], 'unique_usuario_modulo_cert');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificaciones');
    }
};

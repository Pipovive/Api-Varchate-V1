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
        Schema::create('lecciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('modulo_id');
            $table->string('titulo');
            $table->string('slug');
            $table->longText('contenido');
            $table->integer('orden')->nullable()->default(0);
            $table->boolean('tiene_editor_codigo')->nullable()->default(false);
            $table->boolean('tiene_ejercicios')->nullable()->default(false);
            $table->integer('cantidad_ejercicios')->nullable()->default(0);
            $table->enum('estado', ['activo', 'inactivo'])->nullable()->default('activo');
            $table->unsignedBigInteger('created_by')->index('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->index(['modulo_id', 'orden'], 'idx_modulo_orden');
            $table->unique(['modulo_id', 'slug'], 'unique_modulo_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lecciones');
    }
};

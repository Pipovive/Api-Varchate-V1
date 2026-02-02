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
        Schema::create('modulos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('titulo');
            $table->string('slug')->unique('slug');
            $table->longText('descripcion_larga')->nullable();
            $table->enum('modulo', ['introduccion', 'html', 'css', 'javascript', 'php', 'sql'])->index('idx_modulo');
            $table->integer('orden_global')->nullable()->default(0)->index('idx_orden');
            $table->enum('estado', ['activo', 'inactivo', 'borrador'])->nullable()->default('borrador');
            $table->integer('total_lecciones')->nullable()->default(0);
            $table->unsignedBigInteger('created_by')->index('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modulos');
    }
};

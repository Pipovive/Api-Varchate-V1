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
        Schema::create('opciones_ejercicio', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ejercicio_id')->index('idx_ejercicio');
            $table->text('texto');
            $table->boolean('es_correcta')->nullable()->default(false);
            $table->integer('orden')->nullable()->default(0);
            $table->string('pareja_arrastre')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opciones_ejercicio');
    }
};

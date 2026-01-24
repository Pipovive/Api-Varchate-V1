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
        Schema::table('opciones_ejercicio', function (Blueprint $table) {
            $table->foreign(['ejercicio_id'], 'opciones_ejercicio_ibfk_1')->references(['id'])->on('ejercicios')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opciones_ejercicio', function (Blueprint $table) {
            $table->dropForeign('opciones_ejercicio_ibfk_1');
        });
    }
};

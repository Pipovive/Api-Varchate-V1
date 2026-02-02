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
        Schema::table('progreso_lecciones', function (Blueprint $table) {
            $table->foreign(['usuario_id'], 'progreso_lecciones_ibfk_1')->references(['id'])->on('usuarios')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['leccion_id'], 'progreso_lecciones_ibfk_2')->references(['id'])->on('lecciones')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('progreso_lecciones', function (Blueprint $table) {
            $table->dropForeign('progreso_lecciones_ibfk_1');
            $table->dropForeign('progreso_lecciones_ibfk_2');
        });
    }
};

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
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->foreign(['modulo_id'], 'evaluaciones_ibfk_1')->references(['id'])->on('modulos')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['created_by'], 'evaluaciones_ibfk_2')->references(['id'])->on('usuarios')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropForeign('evaluaciones_ibfk_1');
            $table->dropForeign('evaluaciones_ibfk_2');
        });
    }
};

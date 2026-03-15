<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create categorias_modulos table
        Schema::create('categorias_modulos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        // 2. Populate with existing types
        $existingTypes = [
            ['nombre' => 'Introducción', 'slug' => 'introduccion'],
            ['nombre' => 'HTML', 'slug' => 'html'],
            ['nombre' => 'CSS', 'slug' => 'css'],
            ['nombre' => 'JavaScript', 'slug' => 'javascript'],
            ['nombre' => 'PHP', 'slug' => 'php'],
            ['nombre' => 'SQL', 'slug' => 'sql'],
        ];

        foreach ($existingTypes as $type) {
            DB::table('categorias_modulos')->insert(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 3. Modify modulos table to change enum to varchar
        Schema::table('modulos', function (Blueprint $table) {
            $table->string('modulo', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias_modulos');

        Schema::table('modulos', function (Blueprint $table) {
            $table->enum('modulo', ['introduccion', 'html', 'css', 'javascript', 'php', 'sql'])->change();
        });
    }
};

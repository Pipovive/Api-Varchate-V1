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
        Schema::create('usuarios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre');
            $table->string('email')->unique('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('avatar', 50)->nullable()->default('default');
            $table->enum('rol', ['administrador', 'aprendiz'])->nullable()->default('aprendiz');
            $table->enum('estado', ['activo', 'inactivo'])->nullable()->default('activo');
            $table->enum('tema_preferido', ['claro', 'oscuro'])->nullable()->default('claro');
            $table->enum('proveedor_auth', ['email', 'google', 'facebook'])->nullable()->default('email');
            $table->string('auth_provider_id')->nullable();
            $table->string('token_verificacion', 100)->nullable();
            $table->string('token_restablecimiento', 100)->nullable();
            $table->dateTime('fecha_expiracion_token')->nullable();
            $table->tinyInteger('intentos_fallidos')->nullable()->default(0);
            $table->dateTime('bloqueado_hasta')->nullable();
            $table->dateTime('ultimo_acceso')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->index(['email'], 'idx_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};

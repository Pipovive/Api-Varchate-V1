<?php

namespace App\Models; // Asegúrate de tener el namespace correcto

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable // Cambiar de Model a Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = "usuarios";

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'avatar',
        'proveedor_auth',
        'auth_provider_id',
    ];

    protected $hidden = [
        'password',
        'token_verificacion',
        'token_restablecimiento',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // Laravel 10+ (automáticamente hashea)
    ];
}

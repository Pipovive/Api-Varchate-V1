<?php

namespace App\Models; // Asegúrate de tener el namespace correcto

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Avatar;

class Usuario extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, Notifiable;

    protected $table = "usuarios";

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'avatar_id',
        'proveedor_auth',
        'auth_provider_id',
        'terms_accepted',
        'terms_accepted_at'
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
    protected function avatar()
    {
        return $this->belongTo(Avatar::class);
    }
    public function attempts()
    {
        return $this->hasMany(UserAttempt::class);
    }
}

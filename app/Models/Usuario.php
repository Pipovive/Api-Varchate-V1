<?php

namespace App\Models; // Asegúrate de tener el namespace correcto

use App\Notifications\VerifyEmailCustom;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Avatar;
use App\Models\UserAttempt;
use App\Notifications\ResetPasswordCustom;
use Illuminate\Contracts\Auth\CanResetPassword;

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
        'terms_accepted_at',
        'user_agent'
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
        return $this->belongsTo(Avatar::class);
    }

    public function attempts()
    {
        return $this->hasMany(UserAttempt::class, 'user_id');
    }
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailCustom);
    }
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordCustom($token));
    }
}

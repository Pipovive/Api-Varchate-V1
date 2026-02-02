<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'action',
        'success',
        'ip',
        'user_agent',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}

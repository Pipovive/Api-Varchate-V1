<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Avatar extends Model
{
    protected $table = 'avatars';





    function usuario() {
        return $this->hasMany(Usuario::class);
    }
}

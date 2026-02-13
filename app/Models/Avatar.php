<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Avatar extends Model
{
    protected $table = 'avatars';

    //  Cambiar a plural y nombre correcto
    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'avatar_id');
    }
}

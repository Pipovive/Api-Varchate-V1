<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $table = 'modulos';

    protected $fillable = [
        'titulo',
        'slug',
        'descripcion_larga',
        'modulo',
        'orden_global',
        'estado',
        'created_by',
    ];
}

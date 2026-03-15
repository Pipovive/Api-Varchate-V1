<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaModulo extends Model
{
    use HasFactory;

    protected $table = 'categorias_modulos';

    protected $fillable = [
        'nombre',
        'slug',
    ];

    public function modulos()
    {
        return $this->hasMany(Modulo::class, 'modulo', 'slug');
    }
}

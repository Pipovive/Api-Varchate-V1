<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgresoLeccion extends Model
{
    protected $table = 'progreso_lecciones';

    protected $fillable = [
        'usuario_id',
        'leccion_id',
        'vista',
        'fecha_vista'
    ];

    protected $casts = [
        'vista' => 'boolean',
        'fecha_vista' => 'datetime'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function leccion()
    {
        return $this->belongsTo(Leccion::class, 'leccion_id');
    }
}

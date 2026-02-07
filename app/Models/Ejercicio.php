<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ejercicio extends Model
{
    protected $table = 'ejercicios';

    protected $fillable = [
        'leccion_id',
        'pregunta',
        'tipo',
        'orden',
        'estado',
        'created_by'
    ];

    public function leccion()
    {
        return $this->belongsTo(Leccion::class, 'leccion_id');
    }

    public function opciones()
    {
        return $this->hasMany(OpcionEjercicio::class, 'ejercicio_id')
            ->orderBy('orden');
    }
}

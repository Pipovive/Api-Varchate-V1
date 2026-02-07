<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpcionEjercicio extends Model
{
    protected $table = 'opciones_ejercicio';

    protected $fillable = [
        'ejercicio_id',
        'texto',
        'es_correcta',
        'orden',
        'pareja_arrastre'
    ];

    protected $casts = [
        'es_correcta' => 'boolean'
    ];

    public function ejercicio()
    {
        return $this->belongsTo(Ejercicio::class, 'ejercicio_id');
    }
}

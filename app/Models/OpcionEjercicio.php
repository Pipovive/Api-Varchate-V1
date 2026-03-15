<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpcionEjercicio extends Model
{
    use HasFactory;
    public $timestamps = false;
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

    // Relaciones
    public function ejercicio()
    {
        return $this->belongsTo(Ejercicio::class, 'ejercicio_id');
    }

    public function intentos()
    {
        return $this->hasMany(IntentoEjercicio::class, 'opcion_seleccionada_id');
    }
}

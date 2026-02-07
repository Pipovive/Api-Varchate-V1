<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntentoEjercicio extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'intentos_ejercicios';
    protected $fillable = [
        'usuario_id',
        'ejercicio_id',
        'opcion_seleccionada_id',
        'respuesta_texto',
        'es_correcta'
    ];

    protected $casts = [
        'es_correcta' => 'boolean'
    ];

    // Relaciones
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ejercicio()
    {
        return $this->belongsTo(Ejercicio::class, 'ejercicio_id');
    }

    public function opcionSeleccionada()
    {
        return $this->belongsTo(OpcionEjercicio::class, 'opcion_seleccionada_id');
    }
}

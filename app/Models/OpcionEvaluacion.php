<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpcionEvaluacion extends Model
{
    use HasFactory;

    protected $table = 'opciones_evaluacion';
    public $timestamps = false; // Solo tiene created_at

    protected $fillable = [
        'pregunta_evaluacion_id',
        'texto',
        'es_correcta',
        'orden',
        'pareja_arrastre'
    ];

    protected $casts = [
        'es_correcta' => 'boolean'
    ];

    // Relaciones
    public function pregunta()
    {
        return $this->belongsTo(PreguntaEvaluacion::class, 'pregunta_evaluacion_id');
    }

    public function respuestas()
    {
        return $this->hasMany(RespuestaEvaluacion::class, 'opcion_seleccionada_id');
    }
}

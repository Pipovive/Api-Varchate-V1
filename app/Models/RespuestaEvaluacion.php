<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RespuestaEvaluacion extends Model
{
    use HasFactory;

    protected $table = 'respuestas_evaluacion';
    public $timestamps = false; // Solo tiene created_at

    protected $fillable = [
        'intento_id',
        'pregunta_evaluacion_id',
        'opcion_seleccionada_id',
        'respuesta_texto',
        'es_correcta',
        'puntos_obtenidos'
    ];

    protected $casts = [
        'es_correcta' => 'boolean',
        'puntos_obtenidos' => 'decimal:2'
    ];

    // Para manejar created_at manualmente
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    // Relaciones
    public function intento()
    {
        return $this->belongsTo(IntentoEvaluacion::class, 'intento_id');
    }

    public function pregunta()
    {
        return $this->belongsTo(PreguntaEvaluacion::class, 'pregunta_evaluacion_id');
    }

    public function opcionSeleccionada()
    {
        return $this->belongsTo(OpcionEvaluacion::class, 'opcion_seleccionada_id');
    }
}

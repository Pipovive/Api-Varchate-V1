<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreguntaEvaluacion extends Model
{
    use HasFactory;

    protected $table = 'preguntas_evaluacion';
    public $timestamps = true;

    protected $fillable = [
        'evaluacion_id',
        'pregunta',
        'tipo',
        'puntos',
        'orden',
        'created_by'
    ];

    protected $casts = [
        'puntos' => 'decimal:2'
    ];

    // Relaciones
    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class, 'evaluacion_id');
    }

    public function opciones()
    {
        return $this->hasMany(OpcionEvaluacion::class, 'pregunta_evaluacion_id')
            ->orderBy('orden', 'asc');
    }

    public function respuestas()
    {
        return $this->hasMany(RespuestaEvaluacion::class, 'pregunta_evaluacion_id');
    }

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }

    // Métodos
    public function getOpcionesAleatorias()
    {
        return $this->opciones()->inRandomOrder()->get();
    }

    public function getOpcionCorrecta()
    {
        return $this->opciones()->where('es_correcta', true)->first();
    }
}

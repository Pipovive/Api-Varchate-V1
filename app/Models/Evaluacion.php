<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluacion extends Model
{
    use HasFactory;

    protected $table = 'evaluaciones';
    public $timestamps = true;

    protected $fillable = [
        'modulo_id',
        'titulo',
        'descripcion',
        'numero_preguntas',
        'tiempo_limite',
        'puntaje_minimo',
        'max_intentos',
        'estado',
        'created_by'
    ];

    protected $casts = [
        'puntaje_minimo' => 'decimal:2',
        'estado' => 'string'
    ];

    // Relaciones
    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function preguntas()
    {
        return $this->hasMany(PreguntaEvaluacion::class, 'evaluacion_id')
            ->orderBy('orden', 'asc');
    }

    public function intentos()
    {
        return $this->hasMany(IntentoEvaluacion::class, 'evaluacion_id');
    }

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }

    // Scopes
    public function scopeActiva($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopePorModulo($query, $moduloId)
    {
        return $query->where('modulo_id', $moduloId);
    }

    // Métodos
    public function estaDisponible()
    {
        return $this->estado === 'activo';
    }

    public function getPreguntasAleatorias($limit = null)
    {
        $limit = $limit ?? $this->numero_preguntas;
        return $this->preguntas()
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }
}

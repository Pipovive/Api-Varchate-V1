<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntentoEvaluacion extends Model
{
    use HasFactory;

    protected $table = 'intentos_evaluacion';
    public $timestamps = true;

    protected $fillable = [
        'usuario_id',
        'evaluacion_id',
        'intento_numero',
        'fecha_inicio',
        'fecha_fin',
        'tiempo_utilizado',
        'puntuacion_total',
        'porcentaje_obtenido',
        'preguntas_correctas',
        'preguntas_incorrectas',
        'aprobado',
        'estado'
    ];

    protected $casts = [
        'puntuacion_total' => 'decimal:2',
        'porcentaje_obtenido' => 'decimal:2',
        'aprobado' => 'boolean',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime'
    ];

    // Relaciones
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class, 'evaluacion_id');
    }

    public function respuestas()
    {
        return $this->hasMany(RespuestaEvaluacion::class, 'intento_id');
    }

    // Scopes
    public function scopeCompletados($query)
    {
        return $query->where('estado', 'completado');
    }

    public function scopeAprobados($query)
    {
        return $query->where('aprobado', true);
    }

    public function scopeDelUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopeEnProgreso($query)
    {
        return $query->where('estado', 'en_progreso');
    }

    // Métodos
    public function estaEnProgreso()
    {
        return $this->estado === 'en_progreso';
    }

    public function estaCompletado()
    {
        return $this->estado === 'completado';
    }

    public function estaExpirado()
    {
        if (!$this->fecha_inicio || !$this->evaluacion->tiempo_limite) {
            return false;
        }

        $tiempoTranscurrido = now()->diffInMinutes($this->fecha_inicio);
        return $tiempoTranscurrido > $this->evaluacion->tiempo_limite;
    }

    public function calcularResultado()
    {
        $respuestas = $this->respuestas()->with('opcionSeleccionada')->get();

        $puntosTotales = 0;
        $puntosObtenidos = 0;
        $correctas = 0;
        $incorrectas = 0;

        foreach ($respuestas as $respuesta) {
            $puntosTotales += $respuesta->pregunta->puntos ?? 1;

            if ($respuesta->es_correcta) {
                $puntosObtenidos += $respuesta->puntos_obtenidos;
                $correctas++;
            } else {
                $incorrectas++;
            }
        }

        $porcentaje = $puntosTotales > 0 ? ($puntosObtenidos / $puntosTotales) * 100 : 0;
        $aprobado = $porcentaje >= $this->evaluacion->puntaje_minimo;

        $this->update([
            'puntuacion_total' => $puntosObtenidos,
            'porcentaje_obtenido' => $porcentaje,
            'preguntas_correctas' => $correctas,
            'preguntas_incorrectas' => $incorrectas,
            'aprobado' => $aprobado,
            'estado' => 'completado',
            'fecha_fin' => now(),
            'tiempo_utilizado' => now()->diffInSeconds($this->fecha_inicio)
        ]);

        return $this;
    }
}

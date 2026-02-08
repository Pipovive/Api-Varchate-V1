<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgresoModulo extends Model
{
    protected $table = 'progreso_modulo';

    protected $fillable = [
        'usuario_id',
        'modulo_id',
        'porcentaje_completado',
        'lecciones_vistas',
        'total_lecciones',
        'evaluacion_aprobada',
        'certificado_disponible',
        'ultima_leccion_vista_id',
        'fecha_ultimo_progreso'
    ];

    protected $casts = [
        'porcentaje_completado' => 'decimal:2',
        'evaluacion_aprobada' => 'boolean',
        'certificado_disponible' => 'boolean',
        'fecha_ultimo_progreso' => 'datetime'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function ultimaLeccion()
    {
        return $this->belongsTo(Leccion::class, 'ultima_leccion_vista_id');
    }

    // Método para calcular progreso
    public function calcularProgreso()
    {
        $totalLecciones = $this->modulo->lecciones()->count();

        if ($totalLecciones > 0) {
            $this->porcentaje_completado = ($this->lecciones_vistas / $totalLecciones) * 100;
        } else {
            $this->porcentaje_completado = 0;
        }

        $this->total_lecciones = $totalLecciones;
        $this->save();

        return $this;
    }
}

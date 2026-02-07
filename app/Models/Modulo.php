<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Modulo extends Model
{
    use HasFactory;

    protected $table = 'modulos';

    protected $fillable = [
        'titulo',
        'slug',
        'descripcion_larga',
        'modulo',
        'orden_global',
        'estado',
        'total_lecciones',
        'created_by'
    ];

    // Agregar esta relación (si no existe)
    public function lecciones()
    {
        return $this->hasMany(Leccion::class, 'modulo_id')
            ->where('estado', 'activo')
            ->orderBy('orden');
    }

    // Relación con evaluación (ya debería existir)
    public function evaluacion()
    {
        return $this->hasOne(Evaluacion::class, 'modulo_id')
            ->where('estado', 'activo');
    }

    // Relación con progreso del módulo
    public function progresos()
    {
        return $this->hasMany(ProgresoModulo::class, 'modulo_id');
    }

    // Scope para módulos activos
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    // Scope ordenado
    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden_global');
    }


}

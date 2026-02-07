<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Leccion extends Model
{
    use HasFactory;

    protected $table = 'lecciones';

    protected $fillable = [
        'modulo_id',
        'titulo',
        'slug',
        'contenido',
        'orden',
        'tiene_editor_codigo',
        'tiene_ejercicios',
        'cantidad_ejercicios',
        'estado',
        'created_by'
    ];

    protected $casts = [
        'tiene_editor_codigo' => 'boolean',
        'tiene_ejercicios' => 'boolean',
        'orden' => 'integer',
        'cantidad_ejercicios' => 'integer'
    ];

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function ejercicios()
    {
        return $this->hasMany(Ejercicio::class, 'leccion_id')
            ->where('estado', 'activo')
            ->orderBy('orden');
    }

    public function codigosUsuario()
    {
        return $this->hasMany(CodigoUsuario::class, 'leccion_id');
    }

    public function progresos()
    {
        return $this->hasMany(ProgresoLeccion::class, 'leccion_id');
    }

    // Agrega estos scopes
    public function scopeActivas($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden');
    }
}

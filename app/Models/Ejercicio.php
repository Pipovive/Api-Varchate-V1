<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ejercicio extends Model
{
    use HasFactory;
    public $timestamps = true;
    protected $table = 'ejercicios';
    protected $fillable = [
        'leccion_id',
        'pregunta',
        'tipo',
        'orden',
        'estado',
        'created_by'
    ];

    protected $casts = [
        'estado' => 'string',
        'tipo' => 'string'
    ];

    // Relaciones
    public function leccion()
    {
        return $this->belongsTo(Leccion::class, 'leccion_id');
    }

    public function opciones()
    {
        return $this->hasMany(OpcionEjercicio::class, 'ejercicio_id');
    }

    public function intentos()
    {
        return $this->hasMany(IntentoEjercicio::class, 'ejercicio_id');
    }

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopePorLeccion($query, $leccionId)
    {
        return $query->where('leccion_id', $leccionId);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden', 'asc');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ranking extends Model
{
    use HasFactory;

    protected $table = 'ranking';
    public $timestamps = false;

    protected $fillable = [
        'modulo_id',
        'usuario_id',
        'porcentaje_progreso',
        'posicion',
        'fecha_ultima_actualizacion'
    ];

    protected $casts = [
        'porcentaje_progreso' => 'decimal:2',
        'fecha_ultima_actualizacion' => 'datetime'
    ];

    // Relaciones
    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // Scopes
    public function scopePorModulo($query, $moduloId)
    {
        return $query->where('modulo_id', $moduloId);
    }

    public function scopeTop5($query)
    {
        return $query->orderBy('porcentaje_progreso', 'desc')
                     ->orderBy('fecha_ultima_actualizacion', 'asc')
                     ->limit(5);
    }

    public function scopeActivos($query)
    {
        return $query->where('porcentaje_progreso', '>', 0);
    }
}

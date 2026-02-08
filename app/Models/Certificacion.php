<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificacion extends Model
{
    use HasFactory;

    protected $table = 'certificaciones';
    public $timestamps = true;

    protected $fillable = [
        'usuario_id',
        'modulo_id',
        'intento_evaluacion_id',
        'codigo_certificado',
        'porcentaje_obtenido',
        'fecha_emision',
        'fecha_descarga',
        'descargado',
        'hash_verificacion'
    ];

    protected $casts = [
        'porcentaje_obtenido' => 'decimal:2',
        'fecha_emision' => 'datetime',
        'fecha_descarga' => 'datetime',
        'descargado' => 'boolean'
    ];

    // Relaciones
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function intentoEvaluacion()
    {
        return $this->belongsTo(IntentoEvaluacion::class, 'intento_evaluacion_id');
    }

    // Scopes
    public function scopeDelUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopePorModulo($query, $moduloId)
    {
        return $query->where('modulo_id', $moduloId);
    }

    public function scopeDescargados($query)
    {
        return $query->where('descargado', true);
    }

    public function scopeNoDescargados($query)
    {
        return $query->where('descargado', false);
    }

    public function scopeRecientes($query)
    {
        return $query->orderBy('fecha_emision', 'desc');
    }

    // Métodos
    public function marcarComoDescargado()
    {
        $this->update([
            'descargado' => true,
            'fecha_descarga' => now()
        ]);
    }

    public function esValida()
    {
        // Aquí puedes agregar lógica de validez temporal
        // Por ejemplo, certificados válidos por 2 años
        $fechaExpiracion = $this->fecha_emision->addYears(2);
        return now()->lessThan($fechaExpiracion);
    }

    public function generarHashVerificacion()
    {
        $data = $this->usuario_id . $this->modulo_id . $this->codigo_certificado . time();
        return hash('sha256', $data);
    }

    public function getUrlVerificacion()
    {
        return url("/api/certificaciones/{$this->codigo_certificado}/verificar");
    }
}

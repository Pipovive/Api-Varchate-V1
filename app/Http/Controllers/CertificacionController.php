<?php

namespace App\Http\Controllers;

use App\Models\Certificacion;
use App\Models\Modulo;
use App\Models\ProgresoModulo;
use App\Models\IntentoEvaluacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CertificacionController extends Controller
{
    protected ImageManager $image;

    public function __construct()
    {
        $this->image = new ImageManager(new Driver());
    }

    /**
     * Generar y mostrar imagen del certificado
     * GET /certificaciones/{codigo}/ver
     */
    public function verCertificado(string $codigo)
    {
        try {
            return $this->generarImagenCertificado($codigo);
        } catch (\Throwable $e) {
            \Log::error('Error al ver certificado', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Certificado no encontrado'
            ], 404);
        }
    }

    /**
     * Generar imagen del certificado (método principal)
     */
    private function generarImagenCertificado(string $codigo)
    {
        try {
            $certificacion = Certificacion::with(['usuario', 'modulo'])
                ->where('codigo_certificado', $codigo)
                ->firstOrFail();

            $basePath = storage_path('app/certificados/base.png');

            if (!file_exists($basePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la imagen base'
                ], 404);
            }

            $img = $this->image->read($basePath);
            $w = $img->width();
            $h = $img->height();

            // =========================
            // RUTAS DE FUENTES
            // =========================
            $fontBold = storage_path('app/fonts/BarlowSemiCondensed-Bold.ttf');
            $fontRegular = storage_path('app/fonts/BarlowSemiCondensed-Regular.ttf');

            // Si no existen las fuentes, usar las predeterminadas
            if (!file_exists($fontBold)) {
                $fontBold = null;
            }
            if (!file_exists($fontRegular)) {
                $fontRegular = null;
            }

            // =========================
            // DATOS
            // =========================
            $nombreUsuario = mb_strtoupper($certificacion->usuario->nombre, 'UTF-8');

            // Convertir a mayúsculas pero mantener las tildes correctamente
            $nombreModulo = $this->convertirMayusculasConTildes($certificacion->modulo->titulo);

            // Formatear fecha en español sin "DE"
            $meses = [
                'January' => 'ENERO',
                'February' => 'FEBRERO',
                'March' => 'MARZO',
                'April' => 'ABRIL',
                'May' => 'MAYO',
                'June' => 'JUNIO',
                'July' => 'JULIO',
                'August' => 'AGOSTO',
                'September' => 'SEPTIEMBRE',
                'October' => 'OCTUBRE',
                'November' => 'NOVIEMBRE',
                'December' => 'DICIEMBRE'
            ];

            $fecha = $certificacion->fecha_emision->format('d') . ' DE ' .
                    $meses[$certificacion->fecha_emision->format('F')] . ' DE ' .
                    $certificacion->fecha_emision->format('Y');

            // Siempre 100%
            $porcentaje = '100%';

            // =========================
            // TEXTO DEL CERTIFICADO
            // =========================
            // NOMBRE DEL USUARIO - BAJADO (regresamos a 0.40)
            $img->text($nombreUsuario, $w / 2, $h * 0.40, function ($font) use ($fontBold) {
                $font->size(90);
                $font->color('#0A2E6D');
                $font->align('center');
                $font->valign('center');
                if ($fontBold) {
                    $font->file($fontBold);
                }
            });

            // Línea decorativa - BAJADA (regresamos a 0.45)
            $img->text('______________________________', $w / 2, $h * 0.45, function ($font) use ($fontRegular) {
                $font->size(36);
                $font->color('#0A2E6D');
                $font->align('center');
                if ($fontRegular) {
                    $font->file($fontRegular);
                }
            });

            // Texto intermedio - BAJADO (regresamos a 0.53)
            $img->text(
                'HA COMPLETADO SATISFACTORIAMENTE EL MÓDULO',
                $w / 2,
                $h * 0.53,
                function ($font) use ($fontRegular) {
                    $font->size(42);
                    $font->color('#1F2937');
                    $font->align('center');
                    $font->valign('center');
                    if ($fontRegular) {
                        $font->file($fontRegular);
                    }
                }
            );

            // NOMBRE DEL MÓDULO - BAJADO (regresamos a 0.62)
            $img->text($nombreModulo, $w / 2, $h * 0.62, function ($font) use ($fontBold) {
                $font->size(56);
                $font->color('#C9A227');
                $font->align('center');
                $font->valign('center');
                if ($fontBold) {
                    $font->file($fontBold);
                }
            });

            // Calificación - BAJADO y SIEMPRE 100%
            $img->text(
                'CON UNA CALIFICACIÓN DE ' . $porcentaje,
                $w / 2,
                $h * 0.70,
                function ($font) use ($fontRegular) {
                    $font->size(32);
                    $font->color('#374151');
                    $font->align('center');
                    $font->valign('center');
                    if ($fontRegular) {
                        $font->file($fontRegular);
                    }
                }
            );

            // Fecha - BAJADO (regresamos a 0.76)
            $img->text(
                'FECHA DE EMISIÓN: ' . $fecha,
                $w / 2,
                $h * 0.76,
                function ($font) use ($fontRegular) {
                    $font->size(28);
                    $font->color('#374151');
                    $font->align('center');
                    $font->valign('center');
                    if ($fontRegular) {
                        $font->file($fontRegular);
                    }
                }
            );

            // Código del certificado (mantenemos en 0.97)
            $img->text(
                'CÓDIGO: ' . $certificacion->codigo_certificado,
                $w * 0.95,
                $h * 0.97,
                function ($font) use ($fontRegular) {
                    $font->size(22);
                    $font->color('#4B5563');
                    $font->align('right');
                    $font->valign('center');
                    if ($fontRegular) {
                        $font->file($fontRegular);
                    }
                }
            );

            // =========================
            // GUARDAR Y RETORNAR
            // =========================
            $outputDir = storage_path('app/certificados/generados');
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $outputPath = "{$outputDir}/{$codigo}.png";
            $img->save($outputPath, quality: 90);

            // Marcar como descargado si es el usuario autenticado
            try {
                if (Auth::check()) {
                    $certificacion->update([
                        'descargado' => true,
                        'fecha_descarga' => now()
                    ]);
                }
            } catch (\Exception $e) {
                // No interrumpir si falla la actualización
            }

            return response($img->toPng(), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename=certificado-' . $codigo . '.png'
            ]);

        } catch (\Throwable $e) {
            \Log::error('ERROR CERTIFICADO', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Método auxiliar para convertir a mayúsculas manteniendo tildes
     */
    private function convertirMayusculasConTildes(string $texto): string
    {
        $conversiones = [
            'á' => 'Á', 'é' => 'É', 'í' => 'Í', 'ó' => 'Ó', 'ú' => 'Ú',
            'ñ' => 'Ñ', 'ü' => 'Ü',
            'Á' => 'Á', 'É' => 'É', 'Í' => 'Í', 'Ó' => 'Ó', 'Ú' => 'Ú',
            'Ñ' => 'Ñ', 'Ü' => 'Ü'
        ];

        $texto = strtr(mb_strtoupper($texto, 'UTF-8'), $conversiones);
        return $texto;
    }

    /**
     * Descargar certificado (imagen PNG)
     * GET /certificaciones/{codigo}/descargar
     */
    public function descargarCertificado(string $codigo)
    {
        try {
            $usuario = Auth::user();

            // Verificar que el certificado pertenece al usuario
            $certificacion = Certificacion::where('codigo_certificado', $codigo)
                ->where('usuario_id', $usuario->id)
                ->firstOrFail();

            $response = $this->generarImagenCertificado($codigo);

            // Cambiar header para descarga
            return $response->header(
                'Content-Disposition',
                'attachment; filename="certificado-' . $codigo . '.png"'
            );

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para descargar este certificado'
            ], 403);
        }
    }

    /**
     * Verificar certificado (datos JSON)
     * GET /certificaciones/{codigo}/verificar
     */
    public function verificarCertificado(string $codigo)
    {
        try {
            $certificacion = Certificacion::with(['usuario', 'modulo'])
                ->where('codigo_certificado', $codigo)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'valido' => true,
                    'certificado' => [
                        'codigo' => $certificacion->codigo_certificado,
                        'usuario' => [
                            'nombre' => $certificacion->usuario->nombre
                        ],
                        'modulo' => [
                            'titulo' => $certificacion->modulo->titulo
                        ],
                        'resultados' => [
                            'porcentaje' => (float) $certificacion->porcentaje_obtenido,
                            'fecha_emision' => $certificacion->fecha_emision->format('d/m/Y')
                        ],
                        'verificado_en' => now()->format('Y-m-d H:i:s')
                    ]
                ]
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Certificado no encontrado'
            ], 404);
        }
    }

    /**
     * Obtener todas mis certificaciones
     * GET /certificaciones
     */
    public function getMisCertificaciones()
    {
        try {
            $usuario = Auth::user();

            $certificaciones = Certificacion::with(['modulo'])
                ->where('usuario_id', $usuario->id)
                ->orderBy('fecha_emision', 'desc')
                ->get()
                ->map(function ($certificacion) {
                    return [
                        'codigo_certificado' => $certificacion->codigo_certificado,
                        'modulo' => [
                            'titulo' => $certificacion->modulo->titulo,
                            'slug' => $certificacion->modulo->slug
                        ],
                        'resultados' => [
                            'porcentaje_obtenido' => (float) $certificacion->porcentaje_obtenido,
                            'fecha_emision' => $certificacion->fecha_emision->format('d/m/Y'),
                            'descargado' => (bool) $certificacion->descargado
                        ],
                        'urls' => [
                            'ver' => url("/api/certificaciones/{$certificacion->codigo_certificado}/ver"),
                            'descargar' => url("/api/certificaciones/{$certificacion->codigo_certificado}/descargar"),
                            'verificar' => url("/api/certificaciones/{$certificacion->codigo_certificado}/verificar")
                        ]
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $certificaciones->count(),
                    'certificaciones' => $certificaciones
                ]
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Error al obtener certificaciones', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener certificaciones'
            ], 500);
        }
    }

    /**
     * Generar nueva certificación
     * POST /modulos/{moduloId}/certificacion/generar
     */
    public function generarCertificacion($moduloId)
    {
        try {
            $usuario = Auth::user();

            // Verificar requisitos
            $progreso = ProgresoModulo::where('usuario_id', $usuario->id)
                ->where('modulo_id', $moduloId)
                ->where('porcentaje_completado', '>=', 100)
                ->where('evaluacion_aprobada', true)
                ->first();

            if (!$progreso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes completar el módulo 100% y aprobar la evaluación final para obtener el certificado.'
                ], 400);
            }

            // Verificar si ya tiene certificación
            $existente = Certificacion::where('usuario_id', $usuario->id)
                ->where('modulo_id', $moduloId)
                ->first();

            if ($existente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya tienes una certificación para este módulo',
                    'data' => [
                        'codigo_existente' => $existente->codigo_certificado,
                        'url' => url("/api/certificaciones/{$existente->codigo_certificado}/ver")
                    ]
                ], 400);
            }

            $modulo = Modulo::findOrFail($moduloId);

            // Obtener último intento aprobado
            $intento = IntentoEvaluacion::where('usuario_id', $usuario->id)
                ->whereHas('evaluacion', function ($q) use ($moduloId) {
                    $q->where('modulo_id', $moduloId);
                })
                ->where('aprobado', true)
                ->orderBy('fecha_fin', 'desc')
                ->firstOrFail();

            // Generar código único
            $codigo = 'CERT-' . strtoupper(substr($modulo->modulo, 0, 4)) . '-' .
                     date('Ymd') . '-' . str_pad($usuario->id, 4, '0', STR_PAD_LEFT);

            // Crear certificación
            $certificacion = Certificacion::create([
                'usuario_id' => $usuario->id,
                'modulo_id' => $moduloId,
                'intento_evaluacion_id' => $intento->id,
                'codigo_certificado' => $codigo,
                'porcentaje_obtenido' => $intento->porcentaje_obtenido,
                'fecha_emision' => now(),
                'hash_verificacion' => hash('sha256', $codigo . time() . $usuario->id),
                'descargado' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => '¡Certificación generada exitosamente!',
                'data' => [
                    'codigo_certificado' => $codigo,
                    'usuario' => $usuario->nombre,
                    'modulo' => $modulo->titulo,
                    'porcentaje' => (float) $intento->porcentaje_obtenido,
                    'fecha_emision' => now()->format('d/m/Y H:i:s'),
                    'urls' => [
                        'ver_imagen' => url("/api/certificaciones/{$codigo}/ver"),
                        'descargar' => url("/api/certificaciones/{$codigo}/descargar"),
                        'verificar' => url("/api/certificaciones/{$codigo}/verificar")
                    ]
                ]
            ], 201);

        } catch (\Throwable $e) {
            \Log::error('Error al generar certificación', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar certificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Previsualizar certificado (para ajustar coordenadas)
     * GET /certificaciones/preview
     */
    public function previewCertificado()
    {
        try {
            // Ruta de la imagen base
            $basePath = storage_path('app/certificados/base.png');

            if (!file_exists($basePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Imagen base no encontrada'
                ], 404);
            }

            $img = $this->image->read($basePath);
            $w = $img->width();
            $h = $img->height();

            // Ruta de fuentes
            $fontBold = storage_path('app/fonts/BarlowSemiCondensed-Bold.ttf');
            $fontRegular = storage_path('app/fonts/BarlowSemiCondensed-Regular.ttf');

            if (!file_exists($fontBold)) {
                $fontBold = null;
            }
            if (!file_exists($fontRegular)) {
                $fontRegular = null;
            }

            // Datos de prueba
            $nombre = 'JUAN CARLOS PÉREZ GARCÍA';
            $modulo = $this->convertirMayusculasConTildes('Introducción a HTML5 y CSS3 Avanzado');

            // Fecha en español
            $meses = [
                'January' => 'ENERO',
                'February' => 'FEBRERO',
                'March' => 'MARZO',
                'April' => 'ABRIL',
                'May' => 'MAYO',
                'June' => 'JUNIO',
                'July' => 'JULIO',
                'August' => 'AGOSTO',
                'September' => 'SEPTIEMBRE',
                'October' => 'OCTUBRE',
                'November' => 'NOVIEMBRE',
                'December' => 'DICIEMBRE'
            ];
            $fecha = date('d') . ' DE ' . $meses[date('F')] . ' DE ' . date('Y');

            $codigo = 'CERT-HTML-20250206-0010';

            // Dibujar todo el certificado de prueba
            // Nombre - BAJADO
            $img->text($nombre, $w / 2, $h * 0.40, function ($font) use ($fontBold) {
                $font->size(90);
                $font->color('#0A2E6D');
                $font->align('center');
                $font->valign('center');
                if ($fontBold) {
                    $font->file($fontBold);
                }
            });

            // Línea - BAJADA
            $img->text('______________________________', $w / 2, $h * 0.45, function ($font) use ($fontRegular) {
                $font->size(36);
                $font->color('#0A2E6D');
                $font->align('center');
                if ($fontRegular) {
                    $font->file($fontRegular);
                }
            });

            // Texto - BAJADO
            $img->text('HA COMPLETADO SATISFACTORIAMENTE EL MÓDULO', $w / 2, $h * 0.53, function ($font) use ($fontRegular) {
                $font->size(42);
                $font->color('#1F2937');
                $font->align('center');
                $font->valign('center');
                if ($fontRegular) {
                    $font->file($fontRegular);
                }
            });

            // Módulo - BAJADO
            $img->text($modulo, $w / 2, $h * 0.62, function ($font) use ($fontBold) {
                $font->size(56);
                $font->color('#C9A227');
                $font->align('center');
                $font->valign('center');
                if ($fontBold) {
                    $font->file($fontBold);
                }
            });

            // Calificación - BAJADO y SIEMPRE 100%
            $img->text('CON UNA CALIFICACIÓN DE 100%', $w / 2, $h * 0.70, function ($font) use ($fontRegular) {
                $font->size(32);
                $font->color('#374151');
                $font->align('center');
                $font->valign('center');
                if ($fontRegular) {
                    $font->file($fontRegular);
                }
            });

            // Fecha - BAJADO
            $img->text('FECHA DE EMISIÓN: ' . $fecha, $w / 2, $h * 0.76, function ($font) use ($fontRegular) {
                $font->size(28);
                $font->color('#374151');
                $font->align('center');
                $font->valign('center');
                if ($fontRegular) {
                    $font->file($fontRegular);
                }
            });

            // Código - mantiene posición
            $img->text('CÓDIGO: ' . $codigo, $w * 0.95, $h * 0.97, function ($font) use ($fontRegular) {
                $font->size(22);
                $font->color('#4B5563');
                $font->align('right');
                if ($fontRegular) {
                    $font->file($fontRegular);
                }
            });

            // Info de dimensiones
            $img->text("Dimensiones: {$w}x{$h}px", 50, 50, function ($font) {
                $font->size(20);
                $font->color('#666666');
                $font->align('left');
            });

            return response($img->toPng(), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename=preview-certificado.png'
            ]);

        } catch (\Throwable $e) {
            \Log::error('Error en preview', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error en preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información de la imagen base
     * GET /certificaciones/info-imagen
     */
    public function getInfoImagenBase()
    {
        try {
            $imagenBasePath = storage_path('app/certificados/base.png');

            if (!file_exists($imagenBasePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Imagen base no encontrada'
                ], 404);
            }

            $img = $this->image->read($imagenBasePath);

            return response()->json([
                'success' => true,
                'data' => [
                    'existe' => true,
                    'ruta' => $imagenBasePath,
                    'dimensiones' => [
                        'ancho' => $img->width(),
                        'alto' => $img->height()
                    ],
                    'formato' => 'PNG',
                    'tamano_bytes' => filesize($imagenBasePath),
                    'notas' => 'Las coordenadas en el preview están en porcentajes relativos.'
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}

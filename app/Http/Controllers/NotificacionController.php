<?php
namespace App\Http\Controllers;

use App\Models\Conductor;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificacionController extends Controller
{
    /**
     * Listar notificaciones de un conductor
     */
    public function index($conductorId)
    {
        $conductor = Conductor::findOrFail($conductorId);

        // Obtener notificaciones paginadas
        $notificaciones = $conductor->notificaciones()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('notificaciones.index', [
            'conductor' => $conductor,
            'notificaciones' => $notificaciones
        ]);
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarLeida($id)
    {
        try {
            $notificacion = Notificacion::findOrFail($id);
            $notificacion->leida = true;
            $notificacion->save();

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al marcar notificación: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar notificación'
            ], 500);
        }
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function marcarTodasLeidas($conductorId)
    {
        try {
            $conductor = Conductor::findOrFail($conductorId);
            $conductor->marcarTodasNotificacionesLeidas();

            return response()->json([
                'success' => true,
                'message' => 'Todas las notificaciones marcadas como leídas'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al marcar todas las notificaciones: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar notificaciones'
            ], 500);
        }
    }

    /**
     * Generar notificaciones de prueba
     */
    public function generarNotificacionesPrueba()
    {
        try {
            // Obtener 10 conductores aleatorios
            $conductores = Conductor::inRandomOrder()->limit(10)->get();
            $notificacionesGeneradas = [];

            foreach ($conductores as $conductor) {
                $notificacion = Notificacion::create([
                    'conductor_id' => $conductor->id,
                    'tipo' => $this->generarTipoAleatorio(),
                    'mensaje' => $this->generarMensajeAleatorio(),
                    'leida' => false
                ]);

                $notificacionesGeneradas[] = $notificacion;
            }

            return response()->json([
                'success' => true,
                'message' => 'Notificaciones de prueba generadas',
                'notificaciones' => $notificacionesGeneradas
            ]);
        } catch (\Exception $e) {
            Log::error('Error al generar notificaciones: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al generar notificaciones'
            ], 500);
        }
    }

    /**
     * Métodos privados para generación aleatoria
     */
    private function generarTipoAleatorio()
    {
        $tipos = [
            'FATIGA',
            'PUNTUALIDAD',
            'EFICIENCIA',
            'RUTA_CRITICA',
            'MANTENIMIENTO'
        ];
        return $tipos[array_rand($tipos)];
    }

    private function generarMensajeAleatorio()
    {
        $mensajes = [
            'Alto riesgo de fatiga detectado',
            'Rendimiento por debajo del estándar',
            'Posible necesidad de descanso',
            'Ruta crítica requiere atención inmediata',
            'Verificar condiciones de trabajo'
        ];
        return $mensajes[array_rand($mensajes)];
    }
}

<?php
namespace App\Services;

use App\Models\Conductor;
use App\Models\Validacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function enviarAlertaCritica(Validacion $validacion)
    {
        $conductor = $validacion->conductor;

        Log::channel('alertas')->critical("Alerta Crítica", [
            'conductor_id' => $conductor->id,
            'validacion_id' => $validacion->id,
            'mensaje' => $validacion->mensaje,
            'severidad' => $validacion->severidad
        ]);

        return true;
    }
}

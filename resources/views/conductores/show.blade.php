@extends('layouts.app')

@section('title', 'Conductor: ' . $conductor->nombre . ' - SIPAT')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user text-primary"></i>
        {{ $conductor->nombre }}
        <span class="badge bg-{{ $conductor->estado == 'DISPONIBLE' ? 'success' : ($conductor->estado == 'DESCANSO' ? 'primary' : ($conductor->estado == 'VACACIONES' ? 'warning' : 'danger')) }} ms-2">
            {{ $conductor->estado }}
        </span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="{{ route('conductores.edit', $conductor) }}" class="btn btn-warning btn-sm">
                <i class="fas fa-edit"></i> Editar
            </a>
            <button type="button" class="btn btn-danger btn-sm" onclick="confirmarEliminacion()">
                <i class="fas fa-trash"></i> Eliminar
            </button>
        </div>
        <a href="{{ route('conductores.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver a Lista
        </a>
    </div>
</div>

<div class="row">
    <!-- Información Principal -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-id-card"></i> Información Personal
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Código:</strong></td>
                                <td>{{ $conductor->codigo }}</td>
                            </tr>
                            <tr>
                                <td><strong>Nombre:</strong></td>
                                <td>{{ $conductor->nombre }}</td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>
                                    <a href="mailto:{{ $conductor->email }}">{{ $conductor->email }}</a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Teléfono:</strong></td>
                                <td>
                                    <a href="tel:{{ $conductor->telefono }}">{{ $conductor->telefono }}</a>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Origen:</strong></td>
                                <td>
                                    <i class="fas fa-map-marker-alt text-primary"></i>
                                    {{ $conductor->origen }}
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Estado:</strong></td>
                                <td>
                                    <span class="badge bg-{{ $conductor->estado == 'DISPONIBLE' ? 'success' : ($conductor->estado == 'DESCANSO' ? 'primary' : ($conductor->estado == 'VACACIONES' ? 'warning' : 'danger')) }}">
                                        {{ $conductor->estado }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Licencia:</strong></td>
                                <td>{{ $conductor->licencia }}</td>
                            </tr>
                            <tr>
                                <td><strong>Fecha Ingreso:</strong></td>
                                <td>{{ $conductor->fecha_ingreso->format('d/m/Y') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                @if($conductor->observaciones)
                <div class="mt-3">
                    <h6><i class="fas fa-sticky-note"></i> Observaciones:</h6>
                    <div class="alert alert-light">
                        {{ $conductor->observaciones }}
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Métricas de Rendimiento -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar"></i> Métricas de Rendimiento
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded bg-light">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-clock text-primary me-2"></i>
                                <h4 class="mb-0 text-primary">{{ $conductor->puntualidad }}%</h4>
                            </div>
                            <small class="text-muted">Puntualidad</small>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar bg-primary" style="width: {{ $conductor->puntualidad }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded bg-light">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-tachometer-alt text-success me-2"></i>
                                <h4 class="mb-0 text-success">{{ $conductor->eficiencia }}%</h4>
                            </div>
                            <small class="text-muted">Eficiencia</small>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: {{ $conductor->eficiencia }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded bg-light">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-route text-info me-2"></i>
                                <h4 class="mb-0 text-info">{{ $conductor->rutas_completadas }}</h4>
                            </div>
                            <small class="text-muted">Rutas Completadas</small>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded bg-light">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                <h4 class="mb-0 text-warning">{{ $conductor->incidencias }}</h4>
                            </div>
                            <small class="text-muted">Incidencias</small>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-alt text-muted"></i> Días Acumulados:</span>
                            <span class="badge bg-{{ $conductor->dias_acumulados >= 6 ? 'danger' : 'success' }} fs-6">
                                {{ $conductor->dias_acumulados }}
                                @if($conductor->dias_acumulados >= 6) ⚠️ @endif
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-star text-muted"></i> Score General:</span>
                            <span class="badge bg-primary fs-6">{{ $conductor->score_general }}</span>
                        </div>
                    </div>
                </div>

                @if($conductor->ultima_ruta_corta)
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Última ruta corta: {{ $conductor->ultima_ruta_corta->format('d/m/Y') }}
                    </small>
                </div>
                @endif
            </div>
        </div>

        <!-- Historial de Validaciones -->
        @if($validaciones->count() > 0)
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exclamation-circle"></i> Historial de Validaciones
                </h5>
            </div>
            <div class="card-body">
                @foreach($validaciones as $validacion)
                    <div class="d-flex align-items-start mb-3 p-3 border rounded
                         {{ $validacion->severidad == 'CRITICA' ? 'border-danger bg-danger bg-opacity-10' :
                            ($validacion->severidad == 'ADVERTENCIA' ? 'border-warning bg-warning bg-opacity-10' : 'border-info bg-info bg-opacity-10') }}">
                        <div class="me-3">
                            @if($validacion->severidad == 'CRITICA')
                                <i class="fas fa-exclamation-triangle text-danger"></i>
                            @elseif($validacion->severidad == 'ADVERTENCIA')
                                <i class="fas fa-exclamation-circle text-warning"></i>
                            @else
                                <i class="fas fa-info-circle text-info"></i>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">{{ $validacion->tipo }}</h6>
                                    <p class="mb-1">{{ $validacion->mensaje }}</p>
                                    <small class="text-muted">
                                        {{ $validacion->fecha_deteccion->format('d/m/Y H:i') }}
                                        @if($validacion->fecha_resolucion)
                                            - Resuelto el {{ $validacion->fecha_resolucion->format('d/m/Y H:i') }}
                                            @if($validacion->resuelto_por)
                                                por {{ $validacion->resuelto_por }}
                                            @endif
                                        @endif
                                    </small>
                                </div>
                                <span class="badge bg-{{ $validacion->estado == 'PENDIENTE' ? 'warning' : ($validacion->estado == 'VERIFICADO' ? 'info' : 'success') }}">
                                    {{ $validacion->estado }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- Panel Lateral -->
    <div class="col-md-4">
        <!-- Acciones Rápidas -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-bolt"></i> Acciones Rápidas
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    @if($conductor->estado == 'DISPONIBLE' && $conductor->dias_acumulados >= 6)
                        <button class="btn btn-warning btn-sm" onclick="programarDescanso()">
                            <i class="fas fa-bed"></i> Programar Descanso
                        </button>
                    @endif

                    @if($conductor->estado == 'DESCANSO')
                        <button class="btn btn-success btn-sm" onclick="activarConductor()">
                            <i class="fas fa-play"></i> Activar Conductor
                        </button>
                    @endif

                    <button class="btn btn-info btn-sm" onclick="generarReporte()">
                        <i class="fas fa-file-alt"></i> Generar Reporte
                    </button>

                    <button class="btn btn-secondary btn-sm" onclick="enviarNotificacion()">
                        <i class="fas fa-envelope"></i> Enviar Notificación
                    </button>
                </div>
            </div>
        </div>

        <!-- Estadísticas Adicionales -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-chart-pie"></i> Estadísticas Adicionales
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-bottom pb-2 mb-2">
                            <h5 class="text-info">{{ $conductor->horas_trabajadas }}h</h5>
                            <small class="text-muted">Horas Trabajadas</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border-bottom pb-2 mb-2">
                            <h5 class="text-success">{{ $conductor->fecha_ingreso->diffInDays(now()) }}</h5>
                            <small class="text-muted">Días en Empresa</small>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <small class="text-muted">
                        <strong>Promedio mensual:</strong><br>
                        Rutas: {{ round($conductor->rutas_completadas / max(1, $conductor->fecha_ingreso->diffInMonths(now())), 1) }}<br>
                        Horas: {{ round($conductor->horas_trabajadas / max(1, $conductor->fecha_ingreso->diffInMonths(now())), 1) }}h
                    </small>
                </div>
            </div>
        </div>

        <!-- Alertas -->
        @if($conductor->dias_acumulados >= 6)
        <div class="alert alert-danger">
            <h6><i class="fas fa-exclamation-triangle"></i> ¡Atención!</h6>
            <p class="mb-0">Este conductor tiene {{ $conductor->dias_acumulados }} días acumulados.
            Se recomienda programar descanso inmediatamente.</p>
        </div>
        @endif

        @if($conductor->puntualidad < 90)
        <div class="alert alert-warning">
            <h6><i class="fas fa-clock"></i> Puntualidad Baja</h6>
            <p class="mb-0">La puntualidad está por debajo del 90%. Se recomienda seguimiento.</p>
        </div>
        @endif

        @if($conductor->incidencias > 2)
        <div class="alert alert-info">
            <h6><i class="fas fa-flag"></i> Múltiples Incidencias</h6>
            <p class="mb-0">Este conductor tiene {{ $conductor->incidencias }} incidencias registradas.</p>
        </div>
        @endif
    </div>
</div>

<!-- Modal de Confirmación de Eliminación -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                    Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>¿Está seguro de eliminar al conductor {{ $conductor->nombre }}?</strong></p>
                <p class="text-muted">Esta acción no se puede deshacer y se eliminarán:</p>
                <ul class="text-muted">
                    <li>Todos los datos del conductor</li>
                    <li>Su historial de validaciones</li>
                    <li>Sus métricas de rendimiento</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="{{ route('conductores.destroy', $conductor) }}" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Sí, Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function confirmarEliminacion() {
    const modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
    modal.show();
}

function programarDescanso() {
    Swal.fire({
        title: '¿Programar descanso?',
        text: 'Se cambiará el estado del conductor a DESCANSO y se resetearán los días acumulados.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f0ad4e',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, programar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Hacer petición AJAX para cambiar estado
            fetch(`{{ route('conductores.update', $conductor) }}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    estado: 'DESCANSO',
                    dias_acumulados: 0
                })
            }).then(() => {
                location.reload();
            });
        }
    });
}

function activarConductor() {
    Swal.fire({
        title: '¿Activar conductor?',
        text: 'Se cambiará el estado del conductor a DISPONIBLE.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, activar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Hacer petición AJAX para cambiar estado
            fetch(`{{ route('conductores.update', $conductor) }}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    estado: 'DISPONIBLE'
                })
            }).then(() => {
                location.reload();
            });
        }
    });
}

function generarReporte() {
    Swal.fire({
        title: 'Generando reporte...',
        text: 'Se está preparando el reporte del conductor.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Simular generación de reporte
    setTimeout(() => {
        Swal.fire({
            icon: 'success',
            title: 'Reporte generado',
            text: 'El reporte del conductor se ha generado exitosamente.',
            showCancelButton: true,
            confirmButtonText: 'Descargar',
            cancelButtonText: 'Cerrar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Simular descarga
                window.open(`{{ route('conductores.export') }}?conductor={{ $conductor->id }}`, '_blank');
            }
        });
    }, 2000);
}

function enviarNotificacion() {
    Swal.fire({
        title: 'Enviar notificación',
        input: 'textarea',
        inputLabel: 'Mensaje',
        inputPlaceholder: 'Escribe el mensaje para el conductor...',
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar',
        preConfirm: (mensaje) => {
            if (!mensaje) {
                Swal.showValidationMessage('Por favor ingresa un mensaje');
            }
            return mensaje;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire(
                'Notificación enviada',
                'El mensaje ha sido enviado al conductor.',
                'success'
            );
        }
    });
}
</script>
@endsection

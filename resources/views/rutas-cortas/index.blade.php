@extends('layouts.app')

@section('title', 'Rutas Cortas - SIPAT')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-route text-success"></i>
        Gestión de Rutas Cortas
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" onclick="asignarAutomatico()">
                <i class="fas fa-magic"></i> Asignar Automático
            </button>
            <a href="{{ route('rutas-cortas.configuracion.tramos') }}" class="btn btn-outline-secondary">
                <i class="fas fa-cogs"></i> Configurar Tramos
            </a>
            <a href="{{ route('rutas-cortas.create') }}" class="btn btn-success">
                <i class="fas fa-plus"></i> Nueva Ruta
            </a>
        </div>
    </div>
</div>

<!-- Métricas Principales -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center bg-primary text-white">
            <div class="card-body">
                <h4>{{ $metricas['total_rutas'] }}</h4>
                <small>Total Rutas</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center bg-warning text-white">
            <div class="card-body">
                <h4>{{ $metricas['programadas_hoy'] }}</h4>
                <small>Hoy Programadas</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center bg-success text-white">
            <div class="card-body">
                <h4>{{ $metricas['completadas_semana'] }}</h4>
                <small>Completadas</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center bg-info text-white">
            <div class="card-body">
                <h4>{{ $metricas['conductores_con_rutas'] }}</h4>
                <small>Conductores</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center bg-secondary text-white">
            <div class="card-body">
                <h4>{{ $metricas['promedio_duracion'] }}h</h4>
                <small>Duración Prom.</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center bg-dark text-white">
            <div class="card-body">
                <h4>S/ {{ number_format($metricas['total_ingresos_semana'], 0) }}</h4>
                <small>Ingresos Semana</small>
            </div>
        </div>
    </div>
</div>

<!-- Alertas del Sistema -->
@if($metricas['violaciones_consecutivas'] > 0)
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Atención:</strong> Hay {{ $metricas['violaciones_consecutivas'] }} asignaciones consecutivas esta semana que requieren revisión.
    <a href="{{ route('validaciones.index', ['tipo' => 'RUTAS_CORTAS']) }}" class="alert-link">Ver validaciones</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<!-- Filtros Avanzados -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="fas fa-filter"></i> Filtros de Búsqueda
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('rutas-cortas.index') }}">
            <div class="row">
                <div class="col-md-3">
                    <label for="conductor_id" class="form-label">Conductor</label>
                    <select class="form-select" id="conductor_id" name="conductor_id">
                        <option value="">Todos los conductores</option>
                        @foreach($conductores as $conductor)
                            <option value="{{ $conductor->id }}" {{ request('conductor_id') == $conductor->id ? 'selected' : '' }}>
                                {{ $conductor->codigo }} - {{ $conductor->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="PROGRAMADA" {{ request('estado') == 'PROGRAMADA' ? 'selected' : '' }}>Programada</option>
                        <option value="EN_CURSO" {{ request('estado') == 'EN_CURSO' ? 'selected' : '' }}>En Curso</option>
                        <option value="COMPLETADA" {{ request('estado') == 'COMPLETADA' ? 'selected' : '' }}>Completada</option>
                        <option value="CANCELADA" {{ request('estado') == 'CANCELADA' ? 'selected' : '' }}>Cancelada</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="tramo" class="form-label">Tramo</label>
                    <select class="form-select" id="tramo" name="tramo">
                        <option value="">Todos</option>
                        @foreach($tramos as $tramo)
                            <option value="{{ $tramo }}" {{ request('tramo') == $tramo ? 'selected' : '' }}>
                                {{ $tramo }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="fecha_desde" class="form-label">Desde</label>
                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                           value="{{ request('fecha_desde') }}">
                </div>

                <div class="col-md-2">
                    <label for="fecha_hasta" class="form-label">Hasta</label>
                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                           value="{{ request('fecha_hasta') }}">
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <div class="row mt-2">
                <div class="col-md-2">
                    <label for="semana" class="form-label">Semana</label>
                    <input type="number" class="form-control" id="semana" name="semana"
                           value="{{ request('semana') }}" min="1" max="53" placeholder="Nº semana">
                </div>

                <div class="col-md-8 d-flex align-items-end">
                    <a href="{{ route('rutas-cortas.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Limpiar Filtros
                    </a>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-info w-100" onclick="exportarResultados()">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Rutas Cortas -->
<div class="card">
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="fas fa-list"></i> Lista de Rutas Cortas
        </h6>
    </div>
    <div class="card-body">
        @if($rutasCortas->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Conductor</th>
                            <th>Tramo</th>
                            <th>Fecha & Hora</th>
                            <th>Duración</th>
                            <th>Estado</th>
                            <th>Ingreso Est.</th>
                            <th>Semana</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rutasCortas as $ruta)
                            <tr class="{{ $ruta->es_consecutiva ? 'table-warning' : '' }}">
                                <td>
                                    <div>
                                        <strong>{{ $ruta->conductor->nombre }}</strong>
                                        <br>
                                        <small class="text-muted">
                                            {{ $ruta->conductor->codigo }} • {{ $ruta->conductor->origen }}
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $ruta->rumbo == 'SUR' ? 'primary' : 'info' }}">
                                        {{ $ruta->rumbo }}
                                    </span>
                                    <br>
                                    <strong>{{ $ruta->tramo }}</strong>
                                </td>
                                <td>
                                    <div>
                                        <i class="fas fa-calendar"></i>
                                        {{ $ruta->fecha_asignacion->format('d/m/Y') }}
                                        <br>
                                        <i class="fas fa-clock"></i>
                                        <small class="text-muted">
                                            {{ $ruta->hora_inicio->format('H:i') }} - {{ $ruta->hora_fin->format('H:i') }}
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        {{ $ruta->duracion_horas }}h
                                    </span>
                                    @if($ruta->es_consecutiva)
                                        <br>
                                        <small class="text-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Consecutiva
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $ruta->estado_color }}">
                                        {{ $ruta->estado }}
                                    </span>
                                </td>
                                <td>
                                    <strong>S/ {{ number_format($ruta->ingreso_estimado, 2) }}</strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">
                                        S{{ $ruta->semana_numero }}
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        {{ ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][$ruta->dia_semana] }}
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('rutas-cortas.show', $ruta) }}"
                                           class="btn btn-outline-info" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        @if(in_array($ruta->estado, ['PROGRAMADA', 'EN_CURSO']))
                                            <a href="{{ route('rutas-cortas.edit', $ruta) }}"
                                               class="btn btn-outline-warning" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endif

                                        <button type="button" class="btn btn-outline-success"
                                                onclick="cambiarEstado({{ $ruta->id }}, 'COMPLETADA')"
                                                title="Marcar completada"
                                                {{ $ruta->estado == 'COMPLETADA' ? 'disabled' : '' }}>
                                            <i class="fas fa-check"></i>
                                        </button>

                                        @if($ruta->estado != 'COMPLETADA')
                                            <button type="button" class="btn btn-outline-danger"
                                                    onclick="cancelarRuta({{ $ruta->id }})" title="Cancelar">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    Mostrando {{ $rutasCortas->firstItem() }} a {{ $rutasCortas->lastItem() }}
                    de {{ $rutasCortas->total() }} rutas
                </div>
                {{ $rutasCortas->links() }}
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-route fa-3x text-muted mb-3"></i>
                <h5>No se encontraron rutas cortas</h5>
                <p class="text-muted">No hay rutas que coincidan con los filtros aplicados.</p>
                <a href="{{ route('rutas-cortas.create') }}" class="btn btn-success">
                    <i class="fas fa-plus"></i> Crear primera ruta
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Modal para Asignación Automática -->
<div class="modal fade" id="modalAsignarAutomatico" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-magic"></i> Asignación Automática de Rutas
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAsignarAutomatico">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Información:</h6>
                        <ul class="mb-0 small">
                            <li>Se aplicarán las reglas de negocio automáticamente</li>
                            <li>Máximo 2 rutas por conductor por día</li>
                            <li>No se asignarán rutas consecutivas</li>
                            <li>Se respetará el balance semanal (3-4 rutas)</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <label for="fecha_asignacion" class="form-label">Fecha para asignación:</label>
                        <input type="date" class="form-control" id="fecha_asignacion" name="fecha"
                               value="{{ date('Y-m-d', strtotime('+1 day')) }}" min="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="validar_antes" checked>
                            <label class="form-check-label" for="validar_antes">
                                Validar disponibilidad antes de asignar
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-magic"></i> Asignar Automáticamente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.table-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

.badge {
    font-size: 0.75rem;
}

.card-body .progress {
    height: 0.5rem;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.4rem;
    font-size: 0.8rem;
}

@keyframes pulse-warning {
    0% { background-color: rgba(255, 193, 7, 0.1); }
    50% { background-color: rgba(255, 193, 7, 0.3); }
    100% { background-color: rgba(255, 193, 7, 0.1); }
}

.consecutiva-highlight {
    animation: pulse-warning 2s ease-in-out infinite;
}
</style>
@endsection

@section('scripts')
<script>
function asignarAutomatico() {
    const modal = new bootstrap.Modal(document.getElementById('modalAsignarAutomatico'));
    modal.show();
}

document.getElementById('formAsignarAutomatico').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const fecha = formData.get('fecha');

    Swal.fire({
        title: 'Procesando asignación...',
        text: 'El sistema está asignando rutas automáticamente',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('{{ route("rutas-cortas.asignar-automatico") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ fecha: fecha })
    })
    .then(response => response.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('modalAsignarAutomatico')).hide();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Asignación Completada',
                text: data.message,
                showCancelButton: true,
                confirmButtonText: 'Ver resultados',
                cancelButtonText: 'Cerrar'
            }).then((result) => {
                if (result.isConfirmed) {
                    location.reload();
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error en Asignación',
                text: data.message
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Hubo un problema con la asignación automática.'
        });
    });
});

function cambiarEstado(rutaId, nuevoEstado) {
    Swal.fire({
        title: '¿Confirmar cambio de estado?',
        text: `Se cambiará el estado de la ruta a ${nuevoEstado}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/rutas-cortas/${rutaId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    estado: nuevoEstado,
                    hora_inicio: '06:00'
                })
            })
            .then(response => {
                if (response.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Estado actualizado',
                        text: 'El estado de la ruta ha sido actualizado.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error('Error al actualizar estado');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo actualizar el estado de la ruta.'
                });
            });
        }
    });
}

function cancelarRuta(rutaId) {
    Swal.fire({
        title: '¿Cancelar ruta?',
        text: 'Esta acción marcará la ruta como cancelada',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            cambiarEstado(rutaId, 'CANCELADA');
        }
    });
}

function exportarResultados() {
    const params = new URLSearchParams(window.location.search);
    params.append('export', 'csv');

    window.open(`{{ route('rutas-cortas.index') }}?${params.toString()}`, '_blank');
}

// Resaltar rutas consecutivas
document.addEventListener('DOMContentLoaded', function() {
    // Agregar clase de resaltado a rutas consecutivas
    document.querySelectorAll('.table-warning').forEach(row => {
        row.classList.add('consecutiva-highlight');
    });

    // Auto-actualizar métricas cada 30 segundos
    setInterval(() => {
        fetch('{{ route("api.rutas-cortas.estadisticas") }}')
            .then(response => response.json())
            .then(data => {
                // Actualizar métricas sin recargar la página
                updateMetricas(data.metricas);
            })
            .catch(error => console.log('Error actualizando métricas:', error));
    }, 30000);
});

function updateMetricas(metricas) {
    // Actualizar valores de las métricas
    const metricCards = document.querySelectorAll('.card.text-center h4');
    if (metricCards.length >= 6) {
        metricCards[0].textContent = metricas.total_rutas;
        metricCards[1].textContent = metricas.programadas_hoy;
        metricCards[2].textContent = metricas.completadas_semana;
        metricCards[3].textContent = metricas.conductores_con_rutas;
        metricCards[4].textContent = metricas.promedio_duracion + 'h';
        metricCards[5].textContent = 'S/ ' + new Intl.NumberFormat().format(metricas.total_ingresos_semana);
    }
}
</script>
@endsection

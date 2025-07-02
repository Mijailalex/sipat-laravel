@extends('layouts.app')

@section('title', 'Editar Ruta Corta - SIPAT')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit text-warning"></i>
        Editar Ruta Corta: {{ $rutaCorta->tramo }}
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="{{ route('rutas-cortas.show', $rutaCorta) }}" class="btn btn-outline-info btn-sm">
                <i class="fas fa-eye"></i> Ver Detalles
            </a>
            <a href="{{ route('rutas-cortas.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-route"></i> Editar Información de la Ruta
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('rutas-cortas.update', $rutaCorta) }}" method="POST" id="formEditarRuta">
                    @csrf
                    @method('PUT')

                    <!-- Información no editable -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Conductor</label>
                            <div class="form-control-plaintext">
                                <strong>{{ $rutaCorta->conductor->nombre }}</strong>
                                <br>
                                <small class="text-muted">
                                    {{ $rutaCorta->conductor->codigo }} • {{ $rutaCorta->conductor->origen }}
                                </small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tramo</label>
                            <div class="form-control-plaintext">
                                <span class="badge bg-{{ $rutaCorta->rumbo == 'SUR' ? 'primary' : 'info' }}">
                                    {{ $rutaCorta->rumbo }}
                                </span>
                                <strong>{{ $rutaCorta->tramo }}</strong>
                                <br>
                                <small class="text-muted">{{ $rutaCorta->duracion_horas }}h de duración</small>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Fecha de Asignación</label>
                            <div class="form-control-plaintext">
                                <i class="fas fa-calendar"></i>
                                {{ $rutaCorta->fecha_asignacion->format('d/m/Y') }}
                                <br>
                                <small class="text-muted">
                                    Semana {{ $rutaCorta->semana_numero }} •
                                    {{ ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][$rutaCorta->dia_semana] }}
                                </small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="hora_inicio" class="form-label">
                                Hora de Inicio <span class="text-danger">*</span>
                            </label>
                            <input type="time"
                                   class="form-control @error('hora_inicio') is-invalid @enderror"
                                   id="hora_inicio"
                                   name="hora_inicio"
                                   value="{{ old('hora_inicio', $rutaCorta->hora_inicio->format('H:i')) }}"
                                   required>
                            @error('hora_inicio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Hora de Fin (Calculada)</label>
                            <div id="hora_fin_calculada" class="form-control-plaintext">
                                <span class="badge bg-primary">{{ $rutaCorta->hora_fin->format('H:i') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="estado" class="form-label">
                                Estado <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('estado') is-invalid @enderror"
                                    id="estado"
                                    name="estado"
                                    required>
                                <option value="PROGRAMADA" {{ old('estado', $rutaCorta->estado) == 'PROGRAMADA' ? 'selected' : '' }}>
                                    Programada
                                </option>
                                <option value="EN_CURSO" {{ old('estado', $rutaCorta->estado) == 'EN_CURSO' ? 'selected' : '' }}>
                                    En Curso
                                </option>
                                <option value="COMPLETADA" {{ old('estado', $rutaCorta->estado) == 'COMPLETADA' ? 'selected' : '' }}>
                                    Completada
                                </option>
                                <option value="CANCELADA" {{ old('estado', $rutaCorta->estado) == 'CANCELADA' ? 'selected' : '' }}>
                                    Cancelada
                                </option>
                            </select>
                            @error('estado')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Ingreso Estimado</label>
                            <div class="form-control-plaintext">
                                <h5 class="text-success mb-0">S/ {{ number_format($rutaCorta->ingreso_estimado, 2) }}</h5>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control @error('observaciones') is-invalid @enderror"
                                  id="observaciones"
                                  name="observaciones"
                                  rows="3"
                                  placeholder="Observaciones sobre la ruta...">{{ old('observaciones', $rutaCorta->observaciones) }}</textarea>
                        @error('observaciones')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Alertas de cambio de estado -->
                    <div class="alert alert-info" id="alerta_estado" style="display: none;">
                        <h6><i class="fas fa-info-circle"></i> Cambio de Estado:</h6>
                        <div id="mensaje_estado"></div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('rutas-cortas.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Actualizar Ruta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Estado Actual -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-info-circle"></i> Estado Actual
                </h6>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <span class="badge bg-{{ $rutaCorta->estado_color }} fs-5">
                        {{ $rutaCorta->estado }}
                    </span>

                    @if($rutaCorta->es_consecutiva)
                        <div class="mt-2">
                            <span class="badge bg-warning">
                                <i class="fas fa-exclamation-triangle"></i> Consecutiva
                            </span>
                        </div>
                    @endif
                </div>

                <hr>

                <div class="row text-center">
                    <div class="col-6">
                        <h6 class="text-primary">{{ $rutaCorta->duracion_horas }}h</h6>
                        <small>Duración</small>
                    </div>
                    <div class="col-6">
                        <h6 class="text-success">S/ {{ number_format($rutaCorta->ingreso_estimado, 0) }}</h6>
                        <small>Ingreso</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial de la Ruta -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-history"></i> Historial
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Ruta Creada</h6>
                            <small class="text-muted">{{ $rutaCorta->created_at->format('d/m/Y H:i') }}</small>
                        </div>
                    </div>

                    @if($rutaCorta->updated_at != $rutaCorta->created_at)
                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Última Modificación</h6>
                            <small class="text-muted">{{ $rutaCorta->updated_at->format('d/m/Y H:i') }}</small>
                        </div>
                    </div>
                    @endif

                    @if($rutaCorta->estado == 'COMPLETADA')
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Ruta Completada</h6>
                            <small class="text-muted">{{ $rutaCorta->updated_at->format('d/m/Y H:i') }}</small>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Impacto en Balance Semanal -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-chart-line"></i> Impacto en Balance
                </h6>
            </div>
            <div class="card-body">
                <div id="balance_impacto">
                    <small class="text-muted">Cargando balance semanal...</small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.timeline {
    position: relative;
    padding-left: 20px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -12px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    padding-left: 20px;
}
</style>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const horaInicioInput = document.getElementById('hora_inicio');
    const estadoSelect = document.getElementById('estado');
    const duracionHoras = {{ $rutaCorta->duracion_horas }};

    // Calcular hora fin cuando cambie la hora de inicio
    horaInicioInput.addEventListener('change', function() {
        calcularHoraFin();
    });

    // Mostrar alertas cuando cambie el estado
    estadoSelect.addEventListener('change', function() {
        mostrarAlertaEstado(this.value);
    });

    function calcularHoraFin() {
        const horaInicio = horaInicioInput.value;

        if (horaInicio) {
            const horaInicioDate = new Date(`2024-01-01T${horaInicio}:00`);
            horaInicioDate.setHours(horaInicioDate.getHours() + Math.floor(duracionHoras));
            horaInicioDate.setMinutes(horaInicioDate.getMinutes() + ((duracionHoras % 1) * 60));

            const horaFin = horaInicioDate.toTimeString().slice(0, 5);
            document.getElementById('hora_fin_calculada').innerHTML = `
                <span class="badge bg-primary">${horaFin}</span>
            `;
        }
    }

    function mostrarAlertaEstado(nuevoEstado) {
        const estadoActual = '{{ $rutaCorta->estado }}';
        const alerta = document.getElementById('alerta_estado');
        const mensaje = document.getElementById('mensaje_estado');

        if (nuevoEstado !== estadoActual) {
            let textoMensaje = '';
            let tipoAlerta = 'info';

            switch (nuevoEstado) {
                case 'COMPLETADA':
                    if (estadoActual !== 'COMPLETADA') {
                        textoMensaje = `
                            <ul class="mb-0">
                                <li>Se actualizarán las métricas del conductor</li>
                                <li>Se contabilizará el ingreso estimado</li>
                                <li>Se actualizará el balance semanal</li>
                            </ul>
                        `;
                        tipoAlerta = 'success';
                    }
                    break;

                case 'CANCELADA':
                    textoMensaje = `
                        <ul class="mb-0">
                            <li>La ruta no contará para el balance semanal</li>
                            <li>No se contabilizará el ingreso</li>
                            <li>Se liberará el slot para otras asignaciones</li>
                        </ul>
                    `;
                    tipoAlerta = 'warning';
                    break;

                case 'EN_CURSO':
                    textoMensaje = 'La ruta se marcará como en curso. Asegúrate de completarla cuando termine.';
                    break;

                default:
                    textoMensaje = 'Se cambiará el estado de la ruta.';
            }

            alerta.className = `alert alert-${tipoAlerta}`;
            mensaje.innerHTML = textoMensaje;
            alerta.style.display = 'block';
        } else {
            alerta.style.display = 'none';
        }
    }

    // Cargar balance semanal
    cargarBalanceImpacto();

    function cargarBalanceImpacto() {
        const conductorId = {{ $rutaCorta->conductor_id }};
        const semana = {{ $rutaCorta->semana_numero }};

        fetch(`{{ route("api.rutas-cortas.balance-semanal") }}?conductor_id=${conductorId}&semanas=1`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    const balance = data[0];
                    document.getElementById('balance_impacto').innerHTML = `
                        <div class="row text-center">
                            <div class="col-6">
                                <h6 class="text-primary">${balance.total || 0}</h6>
                                <small>Rutas Totales</small>
                            </div>
                            <div class="col-6">
                                <h6 class="text-success">${balance.completadas || 0}</h6>
                                <small>Completadas</small>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar ${balance.objetivo_cumplido ? 'bg-success' : 'bg-warning'}"
                                 style="width: ${Math.min((balance.total || 0) / 4 * 100, 100)}%"></div>
                        </div>
                        <small class="text-${balance.objetivo_cumplido ? 'success' : 'muted'}">
                            ${balance.objetivo_cumplido ? '✓ Objetivo cumplido' : 'Objetivo: 3-4 rutas/semana'}
                        </small>
                        <hr>
                        <div class="text-center">
                            <strong>S/ ${(balance.total_ingresos || 0).toFixed(2)}</strong>
                            <br>
                            <small class="text-muted">Ingresos de la semana</small>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error cargando balance:', error);
                document.getElementById('balance_impacto').innerHTML = `
                    <small class="text-muted">Error al cargar balance</small>
                `;
            });
    }

    // Inicializar campos
    calcularHoraFin();
});

// Validación del formulario
document.getElementById('formEditarRuta').addEventListener('submit', function(e) {
    e.preventDefault();

    const nuevoEstado = document.getElementById('estado').value;
    const estadoActual = '{{ $rutaCorta->estado }}';

    let titulo = '¿Confirmar cambios?';
    let texto = 'Se actualizará la información de la ruta corta';

    if (nuevoEstado !== estadoActual) {
        if (nuevoEstado === 'COMPLETADA') {
            titulo = '¿Marcar como completada?';
            texto = 'Esto actualizará las métricas del conductor y contabilizará los ingresos.';
        } else if (nuevoEstado === 'CANCELADA') {
            titulo = '¿Cancelar ruta?';
            texto = 'La ruta se marcará como cancelada y no contará para el balance.';
        }
    }

    Swal.fire({
        title: titulo,
        text: texto,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f0ad4e',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loading
            Swal.fire({
                title: 'Actualizando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Enviar formulario
            this.submit();
        }
    });
});
</script>
@endsection

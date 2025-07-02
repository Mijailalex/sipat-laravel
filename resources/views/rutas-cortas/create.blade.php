@extends('layouts.app')

@section('title', 'Nueva Ruta Corta - SIPAT')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus-circle text-success"></i>
        Nueva Ruta Corta
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('rutas-cortas.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Lista
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-route"></i> Información de la Ruta Corta
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('rutas-cortas.store') }}" method="POST" id="formRutaCorta">
                    @csrf

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="conductor_id" class="form-label">
                                Conductor <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('conductor_id') is-invalid @enderror"
                                    id="conductor_id"
                                    name="conductor_id"
                                    required>
                                <option value="">Seleccionar conductor...</option>
                                @foreach($conductores as $conductor)
                                    <option value="{{ $conductor->id }}"
                                            data-origen="{{ $conductor->origen }}"
                                            {{ old('conductor_id') == $conductor->id ? 'selected' : '' }}>
                                        {{ $conductor->codigo }} - {{ $conductor->nombre }} ({{ $conductor->origen }})
                                    </option>
                                @endforeach
                            </select>
                            @error('conductor_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text" id="conductor_info"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Estado del Conductor</label>
                            <div id="conductor_estado" class="form-control-plaintext">
                                <span class="badge bg-secondary">Sin seleccionar</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="tramo" class="form-label">
                                Tramo <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('tramo') is-invalid @enderror"
                                    id="tramo"
                                    name="tramo"
                                    required>
                                <option value="">Seleccionar tramo...</option>
                                @foreach($tramos as $tramo)
                                    <option value="{{ $tramo->tramo }}"
                                            data-duracion="{{ $tramo->duracion_horas }}"
                                            data-rumbo="{{ $tramo->rumbo }}"
                                            data-ingreso="{{ $tramo->ingreso_base }}"
                                            {{ old('tramo') == $tramo->tramo ? 'selected' : '' }}>
                                        {{ $tramo->tramo }} ({{ $tramo->duracion_horas }}h - {{ $tramo->rumbo }})
                                    </option>
                                @endforeach
                            </select>
                            @error('tramo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Información del Tramo</label>
                            <div id="tramo_info" class="form-control-plaintext">
                                <small class="text-muted">Selecciona un tramo para ver detalles</small>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="fecha_asignacion" class="form-label">
                                Fecha de Asignación <span class="text-danger">*</span>
                            </label>
                            <input type="date"
                                   class="form-control @error('fecha_asignacion') is-invalid @enderror"
                                   id="fecha_asignacion"
                                   name="fecha_asignacion"
                                   value="{{ old('fecha_asignacion', date('Y-m-d', strtotime('+1 day'))) }}"
                                   min="{{ date('Y-m-d') }}"
                                   required>
                            @error('fecha_asignacion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="hora_inicio" class="form-label">
                                Hora de Inicio <span class="text-danger">*</span>
                            </label>
                            <input type="time"
                                   class="form-control @error('hora_inicio') is-invalid @enderror"
                                   id="hora_inicio"
                                   name="hora_inicio"
                                   value="{{ old('hora_inicio', '06:00') }}"
                                   required>
                            @error('hora_inicio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Hora de Fin (Calculada)</label>
                            <div id="hora_fin" class="form-control-plaintext">
                                <span class="badge bg-info">Se calculará automáticamente</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control @error('observaciones') is-invalid @enderror"
                                  id="observaciones"
                                  name="observaciones"
                                  rows="3"
                                  placeholder="Observaciones adicionales sobre la ruta...">{{ old('observaciones') }}</textarea>
                        @error('observaciones')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Panel de Validaciones -->
                    <div class="card mb-3" id="panel_validaciones" style="display: none;">
                        <div class="card-header bg-warning">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-exclamation-triangle"></i> Validaciones del Sistema
                            </h6>
                        </div>
                        <div class="card-body" id="validaciones_contenido">
                            <!-- Se llenará vía JavaScript -->
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('rutas-cortas.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-success" id="btn_guardar">
                            <i class="fas fa-save"></i> Crear Ruta Corta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Resumen de Asignación -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-chart-pie"></i> Resumen de la Asignación
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center" id="resumen_asignacion">
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h5 class="text-primary" id="duracion_total">-</h5>
                            <small>Duración (hrs)</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h5 class="text-success" id="ingreso_estimado">-</h5>
                            <small>Ingreso Est.</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h5 class="text-info" id="semana_numero">-</h5>
                            <small>Semana</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h5 class="text-warning" id="dia_semana">-</h5>
                            <small>Día</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Semanal del Conductor -->
        <div class="card mb-4" id="balance_conductor" style="display: none;">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-user-check"></i> Balance Semanal
                </h6>
            </div>
            <div class="card-body">
                <div id="balance_contenido">
                    <!-- Se llenará vía JavaScript -->
                </div>
            </div>
        </div>

        <!-- Reglas de Negocio -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-info-circle"></i> Reglas de Rutas Cortas
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info small">
                    <h6><i class="fas fa-rules"></i> Reglas Principales:</h6>
                    <ul class="mb-0">
                        <li>Máximo <strong>2 rutas</strong> por conductor por día</li>
                        <li><strong>NO consecutivas</strong> (días seguidos)</li>
                        <li>Objetivo: <strong>3-4 rutas</strong> por semana</li>
                        <li>Duración máxima: <strong>5 horas</strong></li>
                        <li>Horario recomendado: <strong>6:00 AM - 10:00 PM</strong></li>
                    </ul>
                </div>

                <div class="alert alert-warning small">
                    <h6><i class="fas fa-exclamation-triangle"></i> Validaciones:</h6>
                    <ul class="mb-0">
                        <li>Se validará disponibilidad del conductor</li>
                        <li>Se verificarán reglas de consecutividad</li>
                        <li>Se calculará el balance semanal</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const conductorSelect = document.getElementById('conductor_id');
    const tramoSelect = document.getElementById('tramo');
    const fechaInput = document.getElementById('fecha_asignacion');
    const horaInput = document.getElementById('hora_inicio');

    // Eventos para validación en tiempo real
    conductorSelect.addEventListener('change', validarAsignacion);
    tramoSelect.addEventListener('change', actualizarInfoTramo);
    fechaInput.addEventListener('change', validarAsignacion);
    horaInput.addEventListener('change', calcularHoraFin);

    function actualizarInfoTramo() {
        const selectedOption = tramoSelect.options[tramoSelect.selectedIndex];

        if (selectedOption.value) {
            const duracion = selectedOption.dataset.duracion;
            const rumbo = selectedOption.dataset.rumbo;
            const ingreso = selectedOption.dataset.ingreso;

            document.getElementById('tramo_info').innerHTML = `
                <div class="small">
                    <span class="badge bg-${rumbo === 'SUR' ? 'primary' : 'info'}">${rumbo}</span>
                    <span class="badge bg-secondary">${duracion}h</span>
                    <span class="badge bg-success">S/ ${ingreso}</span>
                </div>
            `;

            // Actualizar resumen
            document.getElementById('duracion_total').textContent = duracion + 'h';
            document.getElementById('ingreso_estimado').textContent = 'S/ ' + ingreso;

            calcularHoraFin();
        } else {
            document.getElementById('tramo_info').innerHTML = '<small class="text-muted">Selecciona un tramo</small>';
        }
    }

    function calcularHoraFin() {
        const horaInicio = horaInput.value;
        const selectedTramo = tramoSelect.options[tramoSelect.selectedIndex];

        if (horaInicio && selectedTramo.value) {
            const duracion = parseFloat(selectedTramo.dataset.duracion);
            const horaInicioDate = new Date(`2024-01-01T${horaInicio}:00`);
            horaInicioDate.setHours(horaInicioDate.getHours() + Math.floor(duracion));
            horaInicioDate.setMinutes(horaInicioDate.getMinutes() + ((duracion % 1) * 60));

            const horaFin = horaInicioDate.toTimeString().slice(0, 5);
            document.getElementById('hora_fin').innerHTML = `
                <span class="badge bg-primary">${horaFin}</span>
            `;
        }
    }

    function actualizarResumenFecha() {
        const fecha = fechaInput.value;
        if (fecha) {
            const fechaObj = new Date(fecha + 'T00:00:00');
            const semana = getWeekNumber(fechaObj);
            const diaNombre = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][fechaObj.getDay()];

            document.getElementById('semana_numero').textContent = 'S' + semana;
            document.getElementById('dia_semana').textContent = diaNombre;
        }
    }

    function validarAsignacion() {
        const conductorId = conductorSelect.value;
        const fecha = fechaInput.value;

        if (conductorId && fecha) {
            // Mostrar información del conductor
            const selectedConductor = conductorSelect.options[conductorSelect.selectedIndex];
            const origen = selectedConductor.dataset.origen;

            document.getElementById('conductor_estado').innerHTML = `
                <span class="badge bg-success">DISPONIBLE</span>
                <span class="badge bg-info">${origen}</span>
            `;

            document.getElementById('conductor_info').innerHTML = `
                <small class="text-info">
                    <i class="fas fa-info-circle"></i>
                    Validando disponibilidad para ${fecha}...
                </small>
            `;

            // Llamada AJAX para validar
            fetch('{{ route("rutas-cortas.validar-asignacion") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    conductor_id: conductorId,
                    fecha: fecha
                })
            })
            .then(response => response.json())
            .then(data => {
                mostrarValidaciones(data);
                cargarBalanceConductor(conductorId, fecha);
            })
            .catch(error => {
                console.error('Error validando asignación:', error);
            });
        }

        actualizarResumenFecha();
    }

    function mostrarValidaciones(validacion) {
        const panel = document.getElementById('panel_validaciones');
        const contenido = document.getElementById('validaciones_contenido');
        const btnGuardar = document.getElementById('btn_guardar');

        if (validacion.puede) {
            contenido.innerHTML = `
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle"></i>
                    <strong>Validación exitosa:</strong> ${validacion.razon}
                </div>
            `;
            btnGuardar.disabled = false;
            btnGuardar.className = 'btn btn-success';
        } else {
            contenido.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Restricción encontrada:</strong> ${validacion.razon}
                </div>
            `;
            btnGuardar.disabled = true;
            btnGuardar.className = 'btn btn-secondary';
        }

        panel.style.display = 'block';

        document.getElementById('conductor_info').innerHTML = `
            <small class="${validacion.puede ? 'text-success' : 'text-danger'}">
                <i class="fas fa-${validacion.puede ? 'check' : 'times'}-circle"></i>
                ${validacion.razon}
            </small>
        `;
    }

    function cargarBalanceConductor(conductorId, fecha) {
        const fechaObj = new Date(fecha);
        const semana = getWeekNumber(fechaObj);

        fetch(`{{ route("api.rutas-cortas.balance-semanal") }}?conductor_id=${conductorId}&semanas=1`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    const balance = data[0];
                    document.getElementById('balance_contenido').innerHTML = `
                        <div class="row text-center">
                            <div class="col-6">
                                <h6 class="text-primary">${balance.total || 0}</h6>
                                <small>Rutas Asignadas</small>
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
                        <small class="text-muted">
                            Objetivo: 3-4 rutas por semana
                            ${balance.objetivo_cumplido ? ' ✓' : ''}
                        </small>
                    `;
                    document.getElementById('balance_conductor').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error cargando balance:', error);
            });
    }

    function getWeekNumber(date) {
        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        const dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
        return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
    }
});

// Validación del formulario
document.getElementById('formRutaCorta').addEventListener('submit', function(e) {
    e.preventDefault();

    const btnGuardar = document.getElementById('btn_guardar');
    if (btnGuardar.disabled) {
        Swal.fire({
            icon: 'error',
            title: 'No se puede crear la ruta',
            text: 'Hay restricciones que impiden la creación de esta ruta.'
        });
        return false;
    }

    Swal.fire({
        title: '¿Confirmar creación?',
        text: 'Se creará la nueva ruta corta con la información proporcionada',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, crear',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loading
            Swal.fire({
                title: 'Creando ruta...',
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

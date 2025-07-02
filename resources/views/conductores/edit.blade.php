@extends('layouts.app')

@section('title', 'Editar Conductor - SIPAT')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-edit text-warning"></i>
        Editar Conductor: {{ $conductor->nombre }}
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="{{ route('conductores.show', $conductor) }}" class="btn btn-outline-info btn-sm">
                <i class="fas fa-eye"></i> Ver Detalles
            </a>
            <a href="{{ route('conductores.index') }}" class="btn btn-outline-secondary btn-sm">
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
                    <i class="fas fa-user-edit"></i> Información del Conductor
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('conductores.update', $conductor) }}" method="POST" id="formEditarConductor">
                    @csrf
                    @method('PUT')

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="codigo" class="form-label">
                                Código <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control @error('codigo') is-invalid @enderror"
                                   id="codigo"
                                   name="codigo"
                                   value="{{ old('codigo', $conductor->codigo) }}"
                                   required>
                            @error('codigo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select @error('estado') is-invalid @enderror"
                                    id="estado"
                                    name="estado">
                                @foreach($estados as $estado)
                                    <option value="{{ $estado }}" {{ old('estado', $conductor->estado) == $estado ? 'selected' : '' }}>
                                        {{ $estado }}
                                    </option>
                                @endforeach
                            </select>
                            @error('estado')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="nombre" class="form-label">
                            Nombre Completo <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control @error('nombre') is-invalid @enderror"
                               id="nombre"
                               name="nombre"
                               value="{{ old('nombre', $conductor->nombre) }}"
                               required>
                        @error('nombre')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">
                                Email <span class="text-danger">*</span>
                            </label>
                            <input type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   id="email"
                                   name="email"
                                   value="{{ old('email', $conductor->email) }}"
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="telefono" class="form-label">
                                Teléfono <span class="text-danger">*</span>
                            </label>
                            <input type="tel"
                                   class="form-control @error('telefono') is-invalid @enderror"
                                   id="telefono"
                                   name="telefono"
                                   value="{{ old('telefono', $conductor->telefono) }}"
                                   required>
                            @error('telefono')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="origen" class="form-label">Origen</label>
                            <select class="form-select @error('origen') is-invalid @enderror"
                                    id="origen"
                                    name="origen">
                                @foreach($origenes as $origen)
                                    <option value="{{ $origen }}" {{ old('origen', $conductor->origen) == $origen ? 'selected' : '' }}>
                                        {{ $origen }}
                                    </option>
                                @endforeach
                            </select>
                            @error('origen')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="licencia" class="form-label">Licencia</label>
                            <select class="form-select @error('licencia') is-invalid @enderror"
                                    id="licencia"
                                    name="licencia">
                                @foreach($licencias as $licencia)
                                    <option value="{{ $licencia }}" {{ old('licencia', $conductor->licencia) == $licencia ? 'selected' : '' }}>
                                        {{ $licencia }}
                                    </option>
                                @endforeach
                            </select>
                            @error('licencia')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="fecha_ingreso" class="form-label">
                                Fecha de Ingreso <span class="text-danger">*</span>
                            </label>
                            <input type="date"
                                   class="form-control @error('fecha_ingreso') is-invalid @enderror"
                                   id="fecha_ingreso"
                                   name="fecha_ingreso"
                                   value="{{ old('fecha_ingreso', $conductor->fecha_ingreso->format('Y-m-d')) }}"
                                   required>
                            @error('fecha_ingreso')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="dias_acumulados" class="form-label">Días Acumulados</label>
                            <input type="number"
                                   class="form-control @error('dias_acumulados') is-invalid @enderror"
                                   id="dias_acumulados"
                                   name="dias_acumulados"
                                   value="{{ old('dias_acumulados', $conductor->dias_acumulados) }}"
                                   min="0"
                                   max="30">
                            @error('dias_acumulados')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                @if($conductor->dias_acumulados >= 6)
                                    <span class="text-danger">⚠️ Conductor en estado crítico (≥6 días)</span>
                                @else
                                    <span class="text-success">✓ Días acumulados normales</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="puntualidad" class="form-label">Puntualidad (%)</label>
                            <input type="number"
                                   class="form-control @error('puntualidad') is-invalid @enderror"
                                   id="puntualidad"
                                   name="puntualidad"
                                   value="{{ old('puntualidad', $conductor->puntualidad) }}"
                                   min="0"
                                   max="100"
                                   step="0.1">
                            @error('puntualidad')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="eficiencia" class="form-label">Eficiencia (%)</label>
                            <input type="number"
                                   class="form-control @error('eficiencia') is-invalid @enderror"
                                   id="eficiencia"
                                   name="eficiencia"
                                   value="{{ old('eficiencia', $conductor->eficiencia) }}"
                                   min="0"
                                   max="100"
                                   step="0.1">
                            @error('eficiencia')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control @error('observaciones') is-invalid @enderror"
                                  id="observaciones"
                                  name="observaciones"
                                  rows="3">{{ old('observaciones', $conductor->observaciones) }}</textarea>
                        @error('observaciones')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('conductores.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Actualizar Conductor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-chart-line"></i> Estadísticas del Conductor
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h4 class="text-primary">{{ $conductor->puntualidad }}%</h4>
                            <small>Puntualidad</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h4 class="text-success">{{ $conductor->eficiencia }}%</h4>
                            <small>Eficiencia</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h4 class="text-info">{{ $conductor->rutas_completadas }}</h4>
                            <small>Rutas</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h4 class="text-warning">{{ $conductor->incidencias }}</h4>
                            <small>Incidencias</small>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle"></i> Importante:</h6>
                    <ul class="mb-0 small">
                        <li>Los cambios en días acumulados pueden generar validaciones</li>
                        <li>Si cambias a DESCANSO, los días se resetearán a 0</li>
                        <li>Verifica que los datos sean correctos antes de guardar</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-history"></i> Historial Reciente
                </h6>
            </div>
            <div class="card-body">
                <small class="text-muted">
                    <strong>Creado:</strong> {{ $conductor->created_at->format('d/m/Y H:i') }}<br>
                    <strong>Última actualización:</strong> {{ $conductor->updated_at->format('d/m/Y H:i') }}<br>
                    <strong>Tiempo en empresa:</strong> {{ $conductor->fecha_ingreso->diffForHumans() }}
                </small>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Validación de días acumulados
document.getElementById('dias_acumulados').addEventListener('input', function() {
    const dias = parseInt(this.value);
    const formText = this.nextElementSibling;

    if (dias >= 6) {
        formText.innerHTML = '<span class="text-danger">⚠️ Conductor en estado crítico (≥6 días)</span>';

        // Mostrar alerta
        if (dias >= 6) {
            Swal.fire({
                icon: 'warning',
                title: 'Conductor Crítico',
                text: 'Este conductor tiene 6 o más días acumulados. Se recomienda programar descanso.',
                showCancelButton: true,
                confirmButtonText: 'Entendido',
                cancelButtonText: 'Cambiar a Descanso',
            }).then((result) => {
                if (result.dismiss === Swal.DismissReason.cancel) {
                    document.getElementById('estado').value = 'DESCANSO';
                    document.getElementById('dias_acumulados').value = '0';
                    formText.innerHTML = '<span class="text-success">✓ Días acumulados normales</span>';
                }
            });
        }
    } else {
        formText.innerHTML = '<span class="text-success">✓ Días acumulados normales</span>';
    }
});

// Confirmación antes de guardar
document.getElementById('formEditarConductor').addEventListener('submit', function(e) {
    e.preventDefault();

    Swal.fire({
        title: '¿Confirmar cambios?',
        text: 'Se actualizará la información del conductor',
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

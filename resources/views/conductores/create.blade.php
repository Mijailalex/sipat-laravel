@extends('layouts.app')

@section('title', 'Nuevo Conductor - SIPAT')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-plus text-primary"></i>
        Nuevo Conductor
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('conductores.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Lista
        </a>
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
                <form action="{{ route('conductores.store') }}" method="POST" id="formConductor">
                    @csrf

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="codigo" class="form-label">
                                Código <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control @error('codigo') is-invalid @enderror"
                                   id="codigo"
                                   name="codigo"
                                   value="{{ old('codigo') }}"
                                   placeholder="Ej: C001"
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
                                    <option value="{{ $estado }}" {{ old('estado', 'DISPONIBLE') == $estado ? 'selected' : '' }}>
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
                               value="{{ old('nombre') }}"
                               placeholder="Nombre completo del conductor"
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
                                   value="{{ old('email') }}"
                                   placeholder="email@empresa.com"
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
                                   value="{{ old('telefono') }}"
                                   placeholder="+51 987 654 321"
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
                                    <option value="{{ $origen }}" {{ old('origen', 'LIMA') == $origen ? 'selected' : '' }}>
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
                                    <option value="{{ $licencia }}" {{ old('licencia', 'A-IIb') == $licencia ? 'selected' : '' }}>
                                        {{ $licencia }}
                                    </option>
                                @endforeach
                            </select>
                            @error('licencia')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="fecha_ingreso" class="form-label">
                            Fecha de Ingreso <span class="text-danger">*</span>
                        </label>
                        <input type="date"
                               class="form-control @error('fecha_ingreso') is-invalid @enderror"
                               id="fecha_ingreso"
                               name="fecha_ingreso"
                               value="{{ old('fecha_ingreso', date('Y-m-d')) }}"
                               required>
                        @error('fecha_ingreso')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control @error('observaciones') is-invalid @enderror"
                                  id="observaciones"
                                  name="observaciones"
                                  rows="3"
                                  placeholder="Observaciones adicionales...">{{ old('observaciones') }}</textarea>
                        @error('observaciones')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('conductores.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Crear Conductor
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
                    <i class="fas fa-info-circle"></i> Información Importante
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb"></i> Consejos:</h6>
                    <ul class="mb-0 small">
                        <li>El código debe ser único en el sistema</li>
                        <li>La fecha de ingreso debe ser anterior a hoy</li>
                        <li>Asegúrate de que el email sea válido</li>
                        <li>La licencia debe estar vigente</li>
                    </ul>
                </div>

                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle"></i> Validaciones:</h6>
                    <ul class="mb-0 small">
                        <li>Se ejecutarán validaciones automáticas</li>
                        <li>Si el conductor tiene 6+ días trabajados, se creará una alerta</li>
                        <li>El sistema verificará duplicados</li>
                    </ul>
                </div>

                <div class="card mt-3">
                    <div class="card-body text-center">
                        <h6>Estados Disponibles:</h6>
                        <div class="d-flex flex-wrap gap-1 justify-content-center">
                            <span class="badge bg-success">DISPONIBLE</span>
                            <span class="badge bg-primary">DESCANSO</span>
                            <span class="badge bg-warning">VACACIONES</span>
                            <span class="badge bg-danger">SUSPENDIDO</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Validación en tiempo real
document.getElementById('formConductor').addEventListener('submit', function(e) {
    const codigo = document.getElementById('codigo').value;
    const nombre = document.getElementById('nombre').value;
    const email = document.getElementById('email').value;

    if (!codigo || !nombre || !email) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Campos requeridos',
            text: 'Por favor completa todos los campos obligatorios.'
        });
        return false;
    }

    // Mostrar confirmación
    Swal.fire({
        title: 'Creando conductor...',
        text: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
});

// Auto-generar código
document.getElementById('nombre').addEventListener('blur', function() {
    const nombre = this.value;
    const codigoField = document.getElementById('codigo');

    if (nombre && !codigoField.value) {
        // Generar código automático basado en el nombre
        const palabras = nombre.split(' ');
        let codigo = 'C';
        palabras.forEach(palabra => {
            if (palabra.length > 0) {
                codigo += palabra[0].toUpperCase();
            }
        });
        // Agregar número aleatorio
        codigo += String(Math.floor(Math.random() * 100)).padStart(2, '0');
        codigoField.value = codigo;
    }
});
</script>
@endsection

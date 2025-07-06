@extends('layouts.app')

@section('title', 'Crear Parámetro - SIPAT')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-plus"></i> Crear Nuevo Parámetro
    </h1>
    <div>
        <a href="{{ route('parametros.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Lista
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Información del Parámetro</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('parametros.store') }}" method="POST" id="formParametro">
                    @csrf

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-folder text-primary"></i> Categoría *
                                </label>
                                <input type="text"
                                       class="form-control @error('categoria') is-invalid @enderror"
                                       name="categoria"
                                       value="{{ old('categoria') }}"
                                       required
                                       placeholder="Ej: VALIDACIONES, REPORTES, GENERAL"
                                       list="categorias-existentes">

                                <datalist id="categorias-existentes">
                                    @foreach($categorias as $categoria)
                                        <option value="{{ $categoria }}">
                                    @endforeach
                                </datalist>

                                @error('categoria')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Agrupa parámetros relacionados</div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-key text-primary"></i> Clave Única *
                                </label>
                                <input type="text"
                                       class="form-control @error('clave') is-invalid @enderror"
                                       name="clave"
                                       value="{{ old('clave') }}"
                                       required
                                       placeholder="Ej: max_dias_validacion"
                                       pattern="[a-z_]+"
                                       title="Solo letras minúsculas y guiones bajos">

                                @error('clave')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Identificador único (solo letras minúsculas y _)</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-tag text-primary"></i> Nombre Descriptivo *
                        </label>
                        <input type="text"
                               class="form-control @error('nombre') is-invalid @enderror"
                               name="nombre"
                               value="{{ old('nombre') }}"
                               required
                               placeholder="Ej: Máximo días para validación">

                        @error('nombre')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-info-circle text-primary"></i> Descripción
                        </label>
                        <textarea class="form-control @error('descripcion') is-invalid @enderror"
                                  name="descripcion"
                                  rows="3"
                                  placeholder="Descripción detallada del parámetro y su uso...">{{ old('descripcion') }}</textarea>

                        @error('descripcion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-cogs text-primary"></i> Tipo de Dato *
                                </label>
                                <select class="form-select @error('tipo') is-invalid @enderror"
                                        name="tipo"
                                        required
                                        id="tipoParametro">
                                    <option value="">Seleccionar tipo</option>
                                    <option value="STRING" {{ old('tipo') === 'STRING' ? 'selected' : '' }}>
                                        📝 Texto (STRING)
                                    </option>
                                    <option value="INTEGER" {{ old('tipo') === 'INTEGER' ? 'selected' : '' }}>
                                        🔢 Número Entero (INTEGER)
                                    </option>
                                    <option value="DECIMAL" {{ old('tipo') === 'DECIMAL' ? 'selected' : '' }}>
                                        💰 Número Decimal (DECIMAL)
                                    </option>
                                    <option value="BOOLEAN" {{ old('tipo') === 'BOOLEAN' ? 'selected' : '' }}>
                                        ✅ Verdadero/Falso (BOOLEAN)
                                    </option>
                                    <option value="JSON" {{ old('tipo') === 'JSON' ? 'selected' : '' }}>
                                        📋 Objeto JSON (JSON)
                                    </option>
                                    <option value="DATE" {{ old('tipo') === 'DATE' ? 'selected' : '' }}>
                                        📅 Fecha (DATE)
                                    </option>
                                    <option value="TIME" {{ old('tipo') === 'TIME' ? 'selected' : '' }}>
                                        🕐 Hora (TIME)
                                    </option>
                                </select>

                                @error('tipo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-edit text-primary"></i> Valor Actual *
                                </label>
                                <input type="text"
                                       class="form-control @error('valor') is-invalid @enderror"
                                       name="valor"
                                       value="{{ old('valor') }}"
                                       required
                                       id="valorActual"
                                       placeholder="Valor que tendrá el parámetro">

                                @error('valor')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-undo text-primary"></i> Valor por Defecto *
                                </label>
                                <input type="text"
                                       class="form-control @error('valor_por_defecto') is-invalid @enderror"
                                       name="valor_por_defecto"
                                       value="{{ old('valor_por_defecto') }}"
                                       required
                                       id="valorDefecto"
                                       placeholder="Valor de respaldo/original">

                                @error('valor_por_defecto')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="seccionOpciones">
                        <label class="form-label">
                            <i class="fas fa-list text-primary"></i> Opciones Válidas
                        </label>
                        <input type="text"
                               class="form-control @error('opciones') is-invalid @enderror"
                               name="opciones"
                               value="{{ old('opciones') }}"
                               placeholder="opcion1, opcion2, opcion3 (separadas por comas)">

                        @error('opciones')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            <i class="fas fa-lightbulb text-warning"></i>
                            Solo para parámetros con valores limitados. Si se especifica, el parámetro solo podrá tomar estos valores.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-sort-numeric-up text-primary"></i> Orden de Visualización
                                </label>
                                <input type="number"
                                       class="form-control @error('orden_visualizacion') is-invalid @enderror"
                                       name="orden_visualizacion"
                                       value="{{ old('orden_visualizacion', 0) }}"
                                       min="0"
                                       placeholder="0">

                                @error('orden_visualizacion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Orden de aparición (0 = primero)</div>
                            </div>
                        </div>

                        <div class="col-lg-8">
                            <div class="mb-3">
                                <label class="form-label">Configuraciones</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   name="modificable"
                                                   id="modificable"
                                                   {{ old('modificable', true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="modificable">
                                                <i class="fas fa-edit text-success"></i> Modificable por usuarios
                                            </label>
                                            <div class="form-text">Los usuarios podrán cambiar este valor</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   name="visible_interfaz"
                                                   id="visible_interfaz"
                                                   {{ old('visible_interfaz', true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="visible_interfaz">
                                                <i class="fas fa-eye text-info"></i> Visible en interfaz
                                            </label>
                                            <div class="form-text">Aparecerá en las listas públicas</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" id="vistaPrevia" style="display: none;">
                        <h6><i class="fas fa-eye"></i> Vista Previa:</h6>
                        <div id="contenidoPrevia"></div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('parametros.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>

                                <div>
                                    <button type="button" class="btn btn-info" onclick="mostrarVistaPrevia()">
                                        <i class="fas fa-eye"></i> Vista Previa
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Crear Parámetro
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-left-info shadow">
            <div class="card-body">
                <h6 class="text-info"><i class="fas fa-question-circle"></i> Ayuda: Tipos de Datos</h6>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li><strong>STRING:</strong> Texto libre (ej: "Mi empresa")</li>
                            <li><strong>INTEGER:</strong> Números enteros (ej: 100, -5)</li>
                            <li><strong>DECIMAL:</strong> Números con decimales (ej: 15.50)</li>
                            <li><strong>BOOLEAN:</strong> true o false</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li><strong>JSON:</strong> Objetos estructurados (ej: {"clave": "valor"})</li>
                            <li><strong>DATE:</strong> Fechas (ej: 2025-07-04)</li>
                            <li><strong>TIME:</strong> Horas (ej: 14:30:00)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoParametro = document.getElementById('tipoParametro');
    const valorActual = document.getElementById('valorActual');
    const valorDefecto = document.getElementById('valorDefecto');

    tipoParametro.addEventListener('change', function() {
        const tipo = this.value;
        actualizarPlaceholders(tipo);
        mostrarEjemplosValor(tipo);
    });

    function actualizarPlaceholders(tipo) {
        let placeholder = '';
        let ejemplo = '';

        switch(tipo) {
            case 'STRING':
                placeholder = 'Ej: Mi texto aquí';
                ejemplo = 'Mi texto aquí';
                break;
            case 'INTEGER':
                placeholder = 'Ej: 100';
                ejemplo = '100';
                break;
            case 'DECIMAL':
                placeholder = 'Ej: 15.50';
                ejemplo = '15.50';
                break;
            case 'BOOLEAN':
                placeholder = 'true o false';
                ejemplo = 'true';
                break;
            case 'JSON':
                placeholder = 'Ej: {"clave": "valor"}';
                ejemplo = '{"activo": true, "limite": 100}';
                break;
            case 'DATE':
                placeholder = 'Ej: 2025-07-04';
                ejemplo = '2025-07-04';
                break;
            case 'TIME':
                placeholder = 'Ej: 14:30:00';
                ejemplo = '14:30:00';
                break;
        }

        valorActual.placeholder = placeholder;
        valorDefecto.placeholder = placeholder;

        if (!valorActual.value) valorActual.value = ejemplo;
        if (!valorDefecto.value) valorDefecto.value = ejemplo;
    }

    function mostrarEjemplosValor(tipo) {
        const ejemplos = {
            'STRING': ['Texto libre', 'Mi empresa', 'Configuración especial'],
            'INTEGER': ['100', '50', '0', '-10'],
            'DECIMAL': ['15.50', '100.00', '0.25'],
            'BOOLEAN': ['true', 'false'],
            'JSON': ['{"activo": true}', '{"limite": 100, "tipo": "premium"}'],
            'DATE': ['2025-07-04', '2025-12-31'],
            'TIME': ['14:30:00', '06:00:00', '23:59:59']
        };

        if (ejemplos[tipo]) {
            console.log(`Ejemplos para ${tipo}:`, ejemplos[tipo]);
        }
    }
});

function mostrarVistaPrevia() {
    const categoria = document.querySelector('[name="categoria"]').value;
    const clave = document.querySelector('[name="clave"]').value;
    const nombre = document.querySelector('[name="nombre"]').value;
    const tipo = document.querySelector('[name="tipo"]').value;
    const valor = document.querySelector('[name="valor"]').value;
    const modificable = document.querySelector('[name="modificable"]').checked;

    if (!clave || !nombre || !tipo) {
        Swal.fire({
            icon: 'warning',
            title: 'Campos Requeridos',
            text: 'Complete al menos la clave, nombre y tipo para ver la vista previa'
        });
        return;
    }

    const contenido = `
        <div class="row">
            <div class="col-md-2"><strong>Categoría:</strong></div>
            <div class="col-md-4"><span class="badge bg-secondary">${categoria}</span></div>
            <div class="col-md-2"><strong>Clave:</strong></div>
            <div class="col-md-4"><code>${clave}</code></div>
        </div>
        <div class="row mt-2">
            <div class="col-md-2"><strong>Nombre:</strong></div>
            <div class="col-md-4">${nombre}</div>
            <div class="col-md-2"><strong>Tipo:</strong></div>
            <div class="col-md-4"><span class="badge bg-info">${tipo}</span></div>
        </div>
        <div class="row mt-2">
            <div class="col-md-2"><strong>Valor:</strong></div>
            <div class="col-md-4"><strong>${valor}</strong></div>
            <div class="col-md-2"><strong>Modificable:</strong></div>
            <div class="col-md-4">
                <span class="badge bg-${modificable ? 'success' : 'secondary'}">
                    ${modificable ? 'Sí' : 'No'}
                </span>
            </div>
        </div>
    `;

    document.getElementById('contenidoPrevia').innerHTML = contenido;
    document.getElementById('vistaPrevia').style.display = 'block';
}

document.getElementById('formParametro').addEventListener('submit', function(e) {
    const clave = document.querySelector('[name="clave"]').value;

    if (!/^[a-z_]+$/.test(clave)) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Clave Inválida',
            text: 'La clave solo puede contener letras minúsculas y guiones bajos (_)'
        });
        return;
    }

    const tipo = document.querySelector('[name="tipo"]').value;
    const valor = document.querySelector('[name="valor"]').value;

    if (!validarValorSegunTipo(valor, tipo)) {
        e.preventDefault();
        return;
    }
});

function validarValorSegunTipo(valor, tipo) {
    switch(tipo) {
        case 'INTEGER':
            if (!/^-?\d+$/.test(valor)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Valor Inválido',
                    text: 'El valor debe ser un número entero'
                });
                return false;
            }
            break;
        case 'DECIMAL':
            if (!/^-?\d+\.?\d*$/.test(valor)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Valor Inválido',
                    text: 'El valor debe ser un número decimal válido'
                });
                return false;
            }
            break;
        case 'BOOLEAN':
            if (!['true', 'false'].includes(valor.toLowerCase())) {
                Swal.fire({
                    icon: 'error',
                    title: 'Valor Inválido',
                    text: 'El valor debe ser "true" o "false"'
                });
                return false;
            }
            break;
        case 'JSON':
            try {
                JSON.parse(valor);
            } catch(e) {
                Swal.fire({
                    icon: 'error',
                    title: 'JSON Inválido',
                    text: 'El valor debe ser un JSON válido'
                });
                return false;
            }
            break;
    }
    return true;
}
</script>
@endpush

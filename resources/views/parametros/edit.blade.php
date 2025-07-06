@extends('layouts.app')

@section('title', 'Editar Parámetro - SIPAT')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-edit"></i> Editar Parámetro: {{ $parametro->nombre }}
    </h1>
    <div>
        <a href="{{ route('parametros.show', $parametro) }}" class="btn btn-info btn-sm">
            <i class="fas fa-eye"></i> Ver Detalle
        </a>
        <a href="{{ route('parametros.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Lista
        </a>
    </div>
</div>

@if(!$parametro->modificable)
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Advertencia:</strong> Este parámetro está marcado como no modificable.
        Algunos cambios pueden no ser permitidos por el sistema.
    </div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-cog"></i> Información del Parámetro
                </h6>
            </div>
            <div class="card-body">
                <form action="{{ route('parametros.update', $parametro) }}" method="POST" id="formParametro">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-folder text-primary"></i> Categoría *
                                </label>
                                <input type="text"
                                       class="form-control @error('categoria') is-invalid @enderror"
                                       name="categoria"
                                       value="{{ old('categoria', $parametro->categoria) }}"
                                       required
                                       list="categorias-existentes">

                                <datalist id="categorias-existentes">
                                    @foreach($categorias as $categoria)
                                        <option value="{{ $categoria }}">
                                    @endforeach
                                </datalist>

                                @error('categoria')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
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
                                       value="{{ old('clave', $parametro->clave) }}"
                                       required
                                       pattern="[a-z_]+"
                                       title="Solo letras minúsculas y guiones bajos">

                                @error('clave')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i>
                                    Cambiar la clave puede afectar funcionalidades que dependan de ella
                                </div>
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
                               value="{{ old('nombre', $parametro->nombre) }}"
                               required>

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
                                  rows="3">{{ old('descripcion', $parametro->descripcion) }}</textarea>

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
                                    <option value="STRING" {{ old('tipo', $parametro->tipo) === 'STRING' ? 'selected' : '' }}>
                                        📝 Texto (STRING)
                                    </option>
                                    <option value="INTEGER" {{ old('tipo', $parametro->tipo) === 'INTEGER' ? 'selected' : '' }}>
                                        🔢 Número Entero (INTEGER)
                                    </option>
                                    <option value="DECIMAL" {{ old('tipo', $parametro->tipo) === 'DECIMAL' ? 'selected' : '' }}>
                                        💰 Número Decimal (DECIMAL)
                                    </option>
                                    <option value="BOOLEAN" {{ old('tipo', $parametro->tipo) === 'BOOLEAN' ? 'selected' : '' }}>
                                        ✅ Verdadero/Falso (BOOLEAN)
                                    </option>
                                    <option value="JSON" {{ old('tipo', $parametro->tipo) === 'JSON' ? 'selected' : '' }}>
                                        📋 Objeto JSON (JSON)
                                    </option>
                                    <option value="DATE" {{ old('tipo', $parametro->tipo) === 'DATE' ? 'selected' : '' }}>
                                        📅 Fecha (DATE)
                                    </option>
                                    <option value="TIME" {{ old('tipo', $parametro->tipo) === 'TIME' ? 'selected' : '' }}>
                                        🕐 Hora (TIME)
                                    </option>
                                </select>

                                @error('tipo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                    Cambiar el tipo puede invalidar el valor actual
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-edit text-primary"></i> Valor Actual *
                                </label>

                                @if($parametro->tipo === 'BOOLEAN')
                                    <select class="form-select @error('valor') is-invalid @enderror" name="valor" required>
                                        <option value="true" {{ old('valor', $parametro->valor) === 'true' ? 'selected' : '' }}>
                                            ✅ Verdadero (true)
                                        </option>
                                        <option value="false" {{ old('valor', $parametro->valor) === 'false' ? 'selected' : '' }}>
                                            ❌ Falso (false)
                                        </option>
                                    </select>
                                @elseif($parametro->tieneOpciones())
                                    <select class="form-select @error('valor') is-invalid @enderror" name="valor" required>
                                        @foreach($parametro->opciones as $opcion)
                                            <option value="{{ $opcion }}" {{ old('valor', $parametro->valor) === $opcion ? 'selected' : '' }}>
                                                {{ $opcion }}
                                            </option>
                                        @endforeach
                                    </select>
                                @elseif($parametro->tipo === 'JSON')
                                    <textarea class="form-control @error('valor') is-invalid @enderror"
                                              name="valor"
                                              required
                                              rows="4"
                                              id="valorActual">{{ old('valor', $parametro->valor) }}</textarea>
                                @else
                                    <input type="text"
                                           class="form-control @error('valor') is-invalid @enderror"
                                           name="valor"
                                           value="{{ old('valor', $parametro->valor) }}"
                                           required
                                           id="valorActual">
                                @endif

                                @error('valor')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                @if($parametro->tipo === 'JSON')
                                    <div class="form-text">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="formatearJSON()">
                                            <i class="fas fa-code"></i> Formatear JSON
                                        </button>
                                    </div>
                                @endif
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
                                       value="{{ old('valor_por_defecto', $parametro->valor_por_defecto) }}"
                                       required>

                                @error('valor_por_defecto')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <div class="form-text">
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="usarValorDefecto()">
                                        <i class="fas fa-copy"></i> Usar como valor actual
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-list text-primary"></i> Opciones Válidas
                        </label>
                        <input type="text"
                               class="form-control @error('opciones') is-invalid @enderror"
                               name="opciones"
                               value="{{ old('opciones', $parametro->opciones ? implode(', ', $parametro->opciones) : '') }}"
                               placeholder="opcion1, opcion2, opcion3">

                        @error('opciones')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Separar opciones con comas. Dejar vacío para valores libres.
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
                                       value="{{ old('orden_visualizacion', $parametro->orden_visualizacion) }}"
                                       min="0">

                                @error('orden_visualizacion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
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
                                                   {{ old('modificable', $parametro->modificable) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="modificable">
                                                <i class="fas fa-edit text-success"></i> Modificable por usuarios
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   name="visible_interfaz"
                                                   id="visible_interfaz"
                                                   {{ old('visible_interfaz', $parametro->visible_interfaz) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="visible_interfaz">
                                                <i class="fas fa-eye text-info"></i> Visible en interfaz
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <div>
                            <a href="{{ route('parametros.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        </div>

                        <div>
                            @if($parametro->modificable)
                                <button type="button" class="btn btn-warning" onclick="restaurarDefecto()">
                                    <i class="fas fa-undo"></i> Restaurar por Defecto
                                </button>
                            @endif
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-info-circle"></i> Información del Sistema
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>ID:</strong></td>
                        <td>{{ $parametro->id }}</td>
                    </tr>
                    <tr>
                        <td><strong>Creado:</strong></td>
                        <td>{{ $parametro->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Modificado:</strong></td>
                        <td>{{ $parametro->updated_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @if($parametro->modificadoPor)
                        <tr>
                            <td><strong>Por:</strong></td>
                            <td>{{ $parametro->modificadoPor->name }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td><strong>Estado:</strong></td>
                        <td>
                            @if($parametro->esValorDefecto())
                                <span class="badge bg-success">Por Defecto</span>
                            @else
                                <span class="badge bg-info">Modificado</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">
                    <i class="fas fa-eye"></i> Valor Actual Formateado
                </h6>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <div class="h4 mb-0 font-weight-bold text-gray-800">
                        {{ $parametro->valor_formateado }}
                    </div>
                    <small class="text-muted">Como se ve en el sistema</small>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-warning">
                    <i class="fas fa-bolt"></i> Acciones Rápidas
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="validarParametro()">
                        <i class="fas fa-check"></i> Validar Valor
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="verHistorial()">
                        <i class="fas fa-history"></i> Ver Historial
                    </button>
                    <a href="{{ route('parametros.show', $parametro) }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye"></i> Ver Detalle Completo
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function formatearJSON() {
    const textarea = document.getElementById('valorActual');
    try {
        const json = JSON.parse(textarea.value);
        textarea.value = JSON.stringify(json, null, 2);
        Swal.fire({
            icon: 'success',
            title: 'JSON Formateado',
            timer: 1000,
            showConfirmButton: false
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'JSON Inválido',
            text: 'El contenido no es un JSON válido'
        });
    }
}

function usarValorDefecto() {
    const valorDefecto = document.querySelector('[name="valor_por_defecto"]').value;
    const valorActual = document.querySelector('[name="valor"]');

    if (valorActual.tagName === 'TEXTAREA') {
        valorActual.value = valorDefecto;
    } else if (valorActual.tagName === 'SELECT') {
        valorActual.value = valorDefecto;
    } else {
        valorActual.value = valorDefecto;
    }

    Swal.fire({
        icon: 'success',
        title: 'Valor Copiado',
        text: 'Se ha copiado el valor por defecto al valor actual',
        timer: 1500,
        showConfirmButton: false
    });
}

function restaurarDefecto() {
    Swal.fire({
        title: '¿Restaurar valor por defecto?',
        text: 'Se restaurará el valor original del parámetro',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f6c23e',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, restaurar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/parametros/{{ $parametro->id }}/restaurar`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const valorActual = document.querySelector('[name="valor"]');
                    valorActual.value = data.data.nuevo_valor;

                    Swal.fire({
                        icon: 'success',
                        title: 'Restaurado',
                        text: data.message,
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al restaurar parámetro'
                });
            });
        }
    });
}

function validarParametro() {
    const tipo = document.querySelector('[name="tipo"]').value;
    const valor = document.querySelector('[name="valor"]').value;

    let esValido = true;
    let mensaje = '';

    switch(tipo) {
        case 'INTEGER':
            esValido = /^-?\d+$/.test(valor);
            mensaje = esValido ? 'Número entero válido' : 'Debe ser un número entero';
            break;
        case 'DECIMAL':
            esValido = /^-?\d+\.?\d*$/.test(valor);
            mensaje = esValido ? 'Número decimal válido' : 'Debe ser un número decimal';
            break;
        case 'BOOLEAN':
            esValido = ['true', 'false'].includes(valor.toLowerCase());
            mensaje = esValido ? 'Valor booleano válido' : 'Debe ser "true" o "false"';
            break;
        case 'JSON':
            try {
                JSON.parse(valor);
                esValido = true;
                mensaje = 'JSON válido';
            } catch(e) {
                esValido = false;
                mensaje = 'JSON inválido: ' + e.message;
            }
            break;
        default:
            mensaje = 'Valor válido';
    }

    Swal.fire({
        icon: esValido ? 'success' : 'error',
        title: esValido ? 'Validación Exitosa' : 'Validación Fallida',
        text: mensaje
    });
}

function verHistorial() {
    Swal.fire({
        icon: 'info',
        title: 'Historial de Cambios',
        html: `
            <div class="text-left">
                <p><strong>Última modificación:</strong> {{ $parametro->updated_at->format('d/m/Y H:i') }}</p>
                @if($parametro->modificadoPor)
                    <p><strong>Modificado por:</strong> {{ $parametro->modificadoPor->name }}</p>
                @endif
                <p><strong>Valor actual:</strong> {{ $parametro->valor }}</p>
                <p><strong>Valor por defecto:</strong> {{ $parametro->valor_por_defecto }}</p>
                <hr>
                <small class="text-muted">El historial completo de cambios estará disponible en una próxima versión.</small>
            </div>
        `
    });
}

document.getElementById('formParametro').addEventListener('submit', function(e) {
    const tipo = document.querySelector('[name="tipo"]').value;
    const valor = document.querySelector('[name="valor"]').value;

    if (!validarValorSegunTipo(valor, tipo)) {
        e.preventDefault();
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

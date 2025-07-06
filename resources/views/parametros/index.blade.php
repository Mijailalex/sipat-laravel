@extends('layouts.app')

@section('title', 'Parámetros del Sistema - SIPAT')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-cogs"></i> Gestión de Parámetros
    </h1>
    <div>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <i class="fas fa-upload"></i> Importar
        </button>
        <a href="{{ route('parametros.exportar') }}" class="btn btn-info btn-sm">
            <i class="fas fa-download"></i> Exportar
        </a>
        <a href="{{ route('parametros.plantilla') }}" class="btn btn-warning btn-sm">
            <i class="fas fa-file-download"></i> Plantilla
        </a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoParametro">
            <i class="fas fa-plus"></i> Nuevo Parámetro
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Parámetros</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $metricas['total'] }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-cogs fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Modificables</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $metricas['modificables'] }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-edit fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">No Modificables</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $metricas['no_modificables'] }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-lock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Categorías</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $categorias->count() }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-folder fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Acciones Rápidas</h6>
            </div>
            <div class="card-body">
                <button type="button" class="btn btn-secondary btn-sm" onclick="validarConfiguracion()">
                    <i class="fas fa-check"></i> Validar Configuración
                </button>
                <button type="button" class="btn btn-warning btn-sm" onclick="restaurarTodosDefecto()">
                    <i class="fas fa-undo"></i> Restaurar Todos por Defecto
                </button>
                <button type="button" class="btn btn-info btn-sm" onclick="mostrarEstadisticas()">
                    <i class="fas fa-chart-bar"></i> Ver Estadísticas
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">Filtros de Búsqueda</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('parametros.index') }}">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Categoría</label>
                    <select name="categoria" class="form-select">
                        <option value="">Todas las categorías</option>
                        @foreach($categorias as $categoria)
                            <option value="{{ $categoria }}" {{ request('categoria') == $categoria ? 'selected' : '' }}>
                                {{ $categoria }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Búsqueda</label>
                    <input type="text" name="buscar" class="form-control"
                           placeholder="Buscar parámetro..."
                           value="{{ request('buscar') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Modificable</label>
                    <select name="modificable" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" {{ request('modificable') === '1' ? 'selected' : '' }}>Sí</option>
                        <option value="0" {{ request('modificable') === '0' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="{{ route('parametros.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Lista de Parámetros</h6>
    </div>
    <div class="card-body">
        @if($parametros->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th>Clave</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Valor Actual</th>
                            <th>Valor por Defecto</th>
                            <th>Modificable</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($parametros as $parametro)
                            <tr id="parametro-{{ $parametro->id }}">
                                <td>
                                    <span class="badge bg-secondary">{{ $parametro->categoria }}</span>
                                </td>
                                <td>
                                    <code>{{ $parametro->clave }}</code>
                                </td>
                                <td>
                                    <strong>{{ $parametro->nombre }}</strong>
                                    @if($parametro->descripcion)
                                        <br>
                                        <small class="text-muted">{{ Str::limit($parametro->descripcion, 100) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ $parametro->tipo }}</span>
                                </td>
                                <td>
                                    <span class="valor-actual" id="valor-{{ $parametro->id }}">
                                        @if($parametro->tipo === 'BOOLEAN')
                                            <span class="badge bg-{{ $parametro->valor === 'true' ? 'success' : 'danger' }}">
                                                {{ $parametro->valor === 'true' ? 'Verdadero' : 'Falso' }}
                                            </span>
                                        @else
                                            {{ $parametro->valor }}
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted">
                                        @if($parametro->tipo === 'BOOLEAN')
                                            {{ $parametro->valor_por_defecto === 'true' ? 'Verdadero' : 'Falso' }}
                                        @else
                                            {{ $parametro->valor_por_defecto }}
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $parametro->modificable ? 'success' : 'secondary' }}">
                                        {{ $parametro->modificable ? 'Sí' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('parametros.show', $parametro) }}"
                                           class="btn btn-info" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if($parametro->modificable)
                                            <a href="{{ route('parametros.edit', $parametro) }}"
                                               class="btn btn-warning" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-secondary"
                                                    onclick="restaurarDefecto({{ $parametro->id }})"
                                                    title="Restaurar por defecto">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-danger"
                                                    onclick="eliminarParametro({{ $parametro->id }})"
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $parametros->links() }}
            </div>
        @else
            <div class="text-center py-4">
                <i class="fas fa-cogs fa-3x text-muted mb-3"></i>
                <h5>No hay parámetros que mostrar</h5>
                <p class="text-muted">No se encontraron parámetros con los filtros aplicados.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoParametro">
                    <i class="fas fa-plus"></i> Crear Primer Parámetro
                </button>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="modalImportar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importar Configuración de Parámetros</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('parametros.importar') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Importante:</strong> Solo se aceptan archivos JSON con la estructura correcta.
                        Descarga la plantilla si no tienes un archivo de configuración.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Archivo de Configuración *</label>
                        <input type="file" class="form-control" name="archivo"
                               accept=".json" required>
                        <div class="form-text">
                            Archivo JSON con la configuración de parámetros
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="confirmarImportacion" required>
                        <label class="form-check-label" for="confirmarImportacion">
                            Confirmo que quiero importar esta configuración y entiendo que se sobrescribirán
                            los valores actuales de los parámetros modificables.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Importar Configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevoParametro" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear Nuevo Parámetro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('parametros.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Categoría *</label>
                                <input type="text" class="form-control" name="categoria" required
                                       placeholder="Ej: VALIDACIONES">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Clave *</label>
                                <input type="text" class="form-control" name="clave" required
                                       placeholder="Ej: max_dias_validacion">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" name="nombre" required
                               placeholder="Ej: Máximo días para validación">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"
                                  placeholder="Descripción detallada del parámetro..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Tipo *</label>
                                <select class="form-select" name="tipo" required>
                                    <option value="">Seleccionar tipo</option>
                                    <option value="STRING">Texto (STRING)</option>
                                    <option value="INTEGER">Número Entero</option>
                                    <option value="DECIMAL">Número Decimal</option>
                                    <option value="BOOLEAN">Verdadero/Falso</option>
                                    <option value="JSON">JSON</option>
                                    <option value="DATE">Fecha</option>
                                    <option value="TIME">Hora</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Valor Actual *</label>
                                <input type="text" class="form-control" name="valor" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Valor por Defecto *</label>
                                <input type="text" class="form-control" name="valor_por_defecto" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Opciones</label>
                        <input type="text" class="form-control" name="opciones"
                               placeholder="Ej: opcion1, opcion2, opcion3 (separadas por comas)">
                        <div class="form-text">Solo para parámetros con valores limitados</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" name="modificable" checked>
                                <label class="form-check-label">Modificable por usuarios</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Orden de Visualización</label>
                                <input type="number" class="form-control" name="orden_visualizacion" value="0">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Parámetro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function validarConfiguracion() {
    fetch('{{ route("parametros.validar") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const resultado = data.data;
                let mensaje = `Validación completada:\n`;
                mensaje += `- Total parámetros: ${resultado.total_parametros}\n`;
                mensaje += `- Problemas encontrados: ${resultado.problemas_encontrados}\n`;

                if (resultado.configuracion_valida) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Configuración Válida',
                        text: mensaje
                    });
                } else {
                    let problemasTexto = resultado.problemas.map(p =>
                        `• ${p.parametro}: ${p.problema}`
                    ).join('\n');

                    Swal.fire({
                        icon: 'warning',
                        title: 'Problemas Encontrados',
                        text: mensaje + '\nProblemas:\n' + problemasTexto
                    });
                }
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al validar configuración'
            });
        });
}

function restaurarDefecto(parametroId) {
    Swal.fire({
        title: '¿Restaurar por defecto?',
        text: 'Se restaurará el valor original del parámetro',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f6c23e',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, restaurar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/parametros/${parametroId}/restaurar`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`valor-${parametroId}`).textContent = data.data.nuevo_valor;
                    Swal.fire({
                        icon: 'success',
                        title: 'Restaurado',
                        text: data.message,
                        timer: 2000
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

function eliminarParametro(parametroId) {
    Swal.fire({
        title: '¿Eliminar parámetro?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/parametros/${parametroId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`parametro-${parametroId}`).remove();
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        text: data.message,
                        timer: 2000
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
                    text: 'Error al eliminar parámetro'
                });
            });
        }
    });
}

function mostrarEstadisticas() {
    const metricas = @json($metricas);
    let estadisticas = 'Estadísticas del Sistema:\n\n';
    estadisticas += `Total de parámetros: ${metricas.total}\n`;
    estadisticas += `Parámetros modificables: ${metricas.modificables}\n`;
    estadisticas += `Parámetros bloqueados: ${metricas.no_modificables}\n\n`;
    estadisticas += 'Por categoría:\n';

    Object.entries(metricas.por_categoria).forEach(([categoria, total]) => {
        estadisticas += `• ${categoria}: ${total}\n`;
    });

    Swal.fire({
        icon: 'info',
        title: 'Estadísticas del Sistema',
        text: estadisticas
    });
}

setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('show')) {
            setTimeout(() => {
                alert.classList.remove('show');
            }, 5000);
        }
    });
}, 1000);
</script>

@if(!empty($errors->any()))
<script>
Swal.fire({
    icon: 'error',
    title: 'Errores de Validación',
    html: '@foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach'
});
</script>
@endif

@endpush

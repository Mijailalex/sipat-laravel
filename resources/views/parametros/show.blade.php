@extends('layouts.app')

@section('title', 'Detalle del Parámetro - SIPAT')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-cog"></i> {{ $parametro->nombre }}
    </h1>
    <div>
        @if($parametro->modificable)
            <a href="{{ route('parametros.edit', $parametro) }}" class="btn btn-warning btn-sm">
                <i class="fas fa-edit"></i> Editar
            </a>
        @endif
        <a href="{{ route('parametros.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Lista
        </a>
    </div>
</div>

<!-- Información General -->
<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-info-circle"></i> Información General
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Categoría</label>
                            <div>
                                <span class="badge bg-secondary fs-6">
                                    <i class="fas fa-folder"></i> {{ $parametro->categoria }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Clave Única</label>
                            <div>
                                <code class="fs-6">{{ $parametro->clave }}</code>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="copiarTexto('{{ $parametro->clave }}')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted">Nombre</label>
                    <div class="h5">{{ $parametro->nombre }}</div>
                </div>

                @if($parametro->descripcion)
                    <div class="mb-3">
                        <label class="form-label text-muted">Descripción</label>
                        <div class="alert alert-light">
                            <i class="fas fa-info-circle text-info"></i>
                            {{ $parametro->descripcion }}
                        </div>
                    </div>
                @endif

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label text-muted">Tipo de Dato</label>
                            <div>
                                <span class="badge bg-{{ $parametro->color_tipo }} fs-6">
                                    <i class="{{ $parametro->icono_tipo }}"></i> {{ $parametro->tipo }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label text-muted">Estado</label>
                            <div>
                                {!! $parametro->estado_formateado !!}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label text-muted">Orden</label>
                            <div class="h6"># {{ $parametro->orden_visualizacion }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Valores -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">
                    <i class="fas fa-values"></i> Valores
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-check"></i> Valor Actual
                                </h6>
                            </div>
                            <div class="card-body text-center">
                                <div class="h4 mb-2 text-success">
                                    {{ $parametro->valor_formateado }}
                                </div>
                                <small class="text-muted">
                                    Valor sin formato: <code>{{ $parametro->valor }}</code>
                                </small>
                                <br>
                                <button type="button" class="btn btn-sm btn-outline-success mt-2" onclick="copiarTexto('{{ $parametro->valor }}')">
                                    <i class="fas fa-copy"></i> Copiar Valor
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-warning">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0">
                                    <i class="fas fa-undo"></i> Valor por Defecto
                                </h6>
                            </div>
                            <div class="card-body text-center">
                                <div class="h4 mb-2 text-warning">
                                    {{ $parametro->formatearValor($parametro->valor_por_defecto, $parametro->tipo) }}
                                </div>
                                <small class="text-muted">
                                    Valor sin formato: <code>{{ $parametro->valor_por_defecto }}</code>
                                </small>
                                <br>
                                @if($parametro->modificable && !$parametro->esValorDefecto())
                                    <button type="button" class="btn btn-sm btn-warning mt-2" onclick="restaurarDefecto()">
                                        <i class="fas fa-undo"></i> Restaurar
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @if($parametro->esValorDefecto())
                    <div class="alert alert-success mt-3">
                        <i class="fas fa-check-circle"></i>
                        <strong>El parámetro está usando su valor por defecto.</strong>
                    </div>
                @else
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-edit"></i>
                        <strong>El parámetro ha sido modificado desde su valor original.</strong>
                    </div>
                @endif
            </div>
        </div>

        <!-- Opciones (si las tiene) -->
        @if($parametro->tieneOpciones())
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-info">
                        <i class="fas fa-list"></i> Opciones Válidas
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($parametro->opciones as $opcion)
                            <span class="badge bg-{{ $opcion === $parametro->valor ? 'success' : 'light text-dark' }} fs-6">
                                @if($opcion === $parametro->valor)
                                    <i class="fas fa-check"></i>
                                @endif
                                {{ $opcion }}
                            </span>
                        @endforeach
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Este parámetro solo puede tomar uno de estos valores predefinidos.
                        </small>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Panel Lateral -->
    <div class="col-lg-4">
        <!-- Validación -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-shield-alt"></i> Validación
                </h6>
            </div>
            <div class="card-body">
                @if($parametro->validarValorActual())
                    <div class="text-center text-success">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <div><strong>Valor Válido</strong></div>
                        <small>El valor actual es correcto según el tipo de dato</small>
                    </div>
                @else
                    <div class="text-center text-danger">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <div><strong>Valor Inválido</strong></div>
                        <small>El valor actual no cumple con las validaciones</small>
                    </div>
                @endif

                <div class="mt-3">
                    <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="validarParametro()">
                        <i class="fas fa-check"></i> Ejecutar Validación
                    </button>
                </div>
            </div>
        </div>

        <!-- Información del Sistema -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-secondary">
                    <i class="fas fa-database"></i> Información del Sistema
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
                </table>
            </div>
        </div>

        <!-- Acciones -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-warning">
                    <i class="fas fa-bolt"></i> Acciones
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    @if($parametro->modificable)
                        <a href="{{ route('parametros.edit', $parametro) }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Editar Parámetro
                        </a>
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="restaurarDefecto()">
                            <i class="fas fa-undo"></i> Restaurar por Defecto
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarParametro()">
                            <i class="fas fa-trash"></i> Eliminar Parámetro
                        </button>
                    @else
                        <div class="alert alert-warning alert-sm">
                            <i class="fas fa-lock"></i>
                            <strong>Parámetro Protegido</strong><br>
                            <small>Este parámetro no puede ser modificado</small>
                        </div>
                    @endif

                    <button type="button" class="btn btn-outline-info btn-sm" onclick="exportarParametro()">
                        <i class="fas fa-download"></i> Exportar Individual
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="verEnUso()">
                        <i class="fas fa-search"></i> Ver Dónde se Usa
                    </button>
                </div>
            </div>
        </div>

        <!-- Estadísticas de Uso -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-info">
                    <i class="fas fa-chart-line"></i> Estadísticas
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="h5 mb-0 font-weight-bold text-primary">
                            {{ \App\Models\Parametro::where('categoria', $parametro->categoria)->count() }}
                        </div>
                        <small class="text-muted">En esta categoría</small>
                    </div>
                    <div class="col-6">
                        <div class="h5 mb-0 font-weight-bold text-success">
                            {{ \App\Models\Parametro::where('tipo', $parametro->tipo)->count() }}
                        </div>
                        <small class="text-muted">De este tipo</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Parámetros Relacionados -->
<div class="row">
    <div class="col-12">
        @php
            $relacionados = \App\Models\Parametro::where('categoria', $parametro->categoria)
                                                 ->where('id', '!=', $parametro->id)
                                                 ->visible()
                                                 ->ordenado()
                                                 ->limit(5)
                                                 ->get();
        @endphp

        @if($relacionados->count() > 0)
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-sitemap"></i> Parámetros Relacionados ({{ $parametro->categoria }})
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Clave</th>
                                    <th>Tipo</th>
                                    <th>Valor</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($relacionados as $rel)
                                    <tr>
                                        <td>{{ $rel->nombre }}</td>
                                        <td><code>{{ $rel->clave }}</code></td>
                                        <td><span class="badge bg-info">{{ $rel->tipo }}</span></td>
                                        <td>{{ Str::limit($rel->valor_formateado, 30) }}</td>
                                        <td>
                                            <a href="{{ route('parametros.show', $rel) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script>
// Función para copiar texto al portapapeles
function copiarTexto(texto) {
    navigator.clipboard.writeText(texto).then(function() {
        Swal.fire({
            icon: 'success',
            title: 'Copiado',
            text: 'Texto copiado al portapapeles',
            timer: 1500,
            showConfirmButton: false
        });
    }).catch(function() {
        // Fallback para navegadores que no soportan clipboard API
        const textArea = document.createElement('textarea');
        textArea.value = texto;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);

        Swal.fire({
            icon: 'success',
            title: 'Copiado',
            text: 'Texto copiado al portapapeles',
            timer: 1500,
            showConfirmButton: false
        });
    });
}

// Función para validar parámetro
function validarParametro() {
    const parametroId = {{ $parametro->id }};

    Swal.fire({
        title: 'Validando...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Simular validación (aquí podrías hacer una llamada AJAX al servidor)
    setTimeout(() => {
        const esValido = {{ $parametro->validarValorActual() ? 'true' : 'false' }};

        Swal.fire({
            icon: esValido ? 'success' : 'error',
            title: esValido ? 'Validación Exitosa' : 'Validación Fallida',
            text: esValido ? 'El parámetro cumple con todas las validaciones' : 'El parámetro tiene errores de validación',
            showConfirmButton: true
        });
    }, 1000);
}

// Función para restaurar por defecto
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

// Función para eliminar parámetro
function eliminarParametro() {
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
            fetch(`/parametros/{{ $parametro->id }}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        text: data.message,
                        timer: 2000
                    }).then(() => {
                        window.location.href = '/parametros';
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

// Función para exportar parámetro individual
function exportarParametro() {
    const parametro = {
        "{{ $parametro->categoria }}": {
            "{{ $parametro->clave }}": {
                "nombre": "{{ $parametro->nombre }}",
                "descripcion": "{{ $parametro->descripcion }}",
                "tipo": "{{ $parametro->tipo }}",
                "valor_actual": "{{ $parametro->valor }}",
                "valor_por_defecto": "{{ $parametro->valor_por_defecto }}",
                "opciones": @json($parametro->opciones),
                "modificable": {{ $parametro->modificable ? 'true' : 'false' }},
                "orden_visualizacion": {{ $parametro->orden_visualizacion }},
                "exportado_en": "{{ now()->toISOString() }}"
            }
        }
    };

    const blob = new Blob([JSON.stringify(parametro, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `parametro_{{ $parametro->clave }}_{{ now()->format('Y-m-d') }}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    Swal.fire({
        icon: 'success',
        title: 'Exportado',
        text: 'Parámetro exportado exitosamente',
        timer: 2000,
        showConfirmButton: false
    });
}

// Función para ver dónde se usa (placeholder)
function verEnUso() {
    Swal.fire({
        icon: 'info',
        title: 'Uso del Parámetro',
        html: `
            <div class="text-left">
                <p><strong>Clave:</strong> <code>{{ $parametro->clave }}</code></p>
                <p><strong>Categoría:</strong> {{ $parametro->categoria }}</p>
                <hr>
                <p>Para usar este parámetro en el código:</p>
                <pre class="bg-light p-2 rounded"><code>
// Obtener valor
$valor = \\App\\Models\\Parametro::obtenerValor('{{ $parametro->clave }}');

// Establecer valor
\\App\\Models\\Parametro::establecerValor('{{ $parametro->clave }}', 'nuevo_valor');
                </code></pre>
                <hr>
                <small class="text-muted">
                    El análisis completo de dependencias estará disponible en una próxima versión.
                </small>
            </div>
        `,
        width: '600px'
    });
}
</script>
@endpush

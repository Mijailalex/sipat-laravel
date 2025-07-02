@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Gestión de Conductores</h1>

            {{-- Tarjetas de Métricas --}}
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card card-metric">
                        <div class="card-body">
                            <h5 class="card-title">Total Conductores</h5>
                            <p class="card-text display-4">{{ $metricas['total'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-metric">
                        <div class="card-body">
                            <h5 class="card-title">Disponibles</h5>
                            <p class="card-text display-4">{{ $metricas['disponibles'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-metric">
                        <div class="card-body">
                            <h5 class="card-title">En Descanso</h5>
                            <p class="card-text display-4">{{ $metricas['en_descanso'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-metric">
                        <div class="card-body">
                            <h5 class="card-title">Conductores Críticos</h5>
                            <p class="card-text display-4">{{ $metricas['criticos'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filtros y Acciones --}}
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h3 class="card-title">Filtros de Búsqueda</h3>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="{{ route('conductores.create') }}" class="btn btn-success mr-2">
                                <i class="fas fa-plus"></i> Nuevo Conductor
                            </a>
                            <div class="btn-group">
                                <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                    Exportar
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="{{ route('conductores.export') }}">
                                        <i class="fas fa-file-csv"></i> Exportar CSV
                                    </a>
                                    <a class="dropdown-item" href="{{ route('conductores.plantilla') }}">
                                        <i class="fas fa-file-download"></i> Descargar Plantilla
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('conductores.index') }}" method="GET">
                        <div class="row">
                            <div class="col-md-3">
                                <input type="text" name="busqueda" class="form-control"
                                       placeholder="Buscar por nombre, código o email"
                                       value="{{ request('busqueda') }}">
                            </div>
                            <div class="col-md-2">
                                <select name="estado" class="form-control">
                                    <option value="">Estado</option>
                                    @foreach($estados as $estado)
                                        <option value="{{ $estado }}"
                                            {{ request('estado') == $estado ? 'selected' : '' }}>
                                            {{ $estado }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="origen" class="form-control">
                                    <option value="">Origen</option>
                                    @foreach($origenes as $origen)
                                        <option value="{{ $origen }}"
                                            {{ request('origen') == $origen ? 'selected' : '' }}>
                                            {{ $origen }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="dias_acumulados" class="form-control">
                                    <option value="">Días Trabajados</option>
                                    <option value="normales" {{ request('dias_acumulados') == 'normales' ? 'selected' : '' }}>
                                        Normales (0-5 días)
                                    </option>
                                    <option value="criticos" {{ request('dias_acumulados') == 'criticos' ? 'selected' : '' }}>
                                        Críticos (6+ días)
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary mr-2">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                                <a href="{{ route('conductores.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-reset"></i> Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Tabla de Conductores --}}
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Origen</th>
                                    <th>Días Acumulados</th>
                                    <th>Puntualidad</th>
                                    <th>Eficiencia</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($conductores as $conductor)
                                    <tr>
                                        <td>{{ $conductor->codigo }}</td>
                                        <td>{{ $conductor->nombre }}</td>
                                        <td>
                                            <span class="badge
                                                @switch($conductor->estado)
                                                    @case('DISPONIBLE')
                                                        badge-success
                                                        @break
                                                    @case('DESCANSO')
                                                        badge-warning
                                                        @break
                                                    @case('SUSPENDIDO')
                                                        badge-danger
                                                        @break
                                                    @default
                                                        badge-secondary
                                                @endswitch
                                            ">
                                                {{ $conductor->estado }}
                                            </span>
                                        </td>
                                        <td>{{ $conductor->origen }}</td>
                                        <td>{{ $conductor->dias_acumulados }}</td>
                                        <td>{{ number_format($conductor->puntualidad, 1) }}%</td>
                                        <td>{{ number_format($conductor->eficiencia, 1) }}%</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('conductores.show', $conductor) }}"
                                                   class="btn btn-sm btn-info"
                                                   title="Ver Detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('conductores.edit', $conductor) }}"
                                                   class="btn btn-sm btn-warning"
                                                   title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-sm btn-danger"
                                                        onclick="confirmarEliminacion({{ $conductor->id }})"
                                                        title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            No se encontraron conductores
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    {{ $conductores->appends(request()->input())->links() }}
                </div>
            </div>

            {{-- Importar Archivo --}}
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Importar Conductores</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('conductores.importar') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="archivo_conductores" name="archivo_conductores" required>
                                    <label class="custom-file-label" for="archivo_conductores">Seleccionar archivo</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="actualizar_existentes" name="actualizar_existentes">
                                    <label class="form-check-label" for="actualizar_existentes">
                                        Actualizar conductores existentes
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Importar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Función para confirmación de eliminación
    function confirmarEliminacion(conductorId) {
        if(confirm('¿Está seguro de eliminar este conductor?')) {
            // Enviar solicitud de eliminación
            fetch(`/conductores/${conductorId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => {
                if(response.ok) {
                    location.reload();
                }
            });
        }
    }

    // Personalizar nombre de archivo en input de importación
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
    });
</script>
@endpush

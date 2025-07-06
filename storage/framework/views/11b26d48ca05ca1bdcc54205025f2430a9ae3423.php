<?php $__env->startSection('title', 'Gestión de Conductores - SIPAT'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">
                    <i class="fas fa-users"></i> Gestión de Conductores
                </h1>
                <div class="btn-group">
                    <a href="<?php echo e(route('conductores.create')); ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuevo Conductor
                    </a>
                    <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="<?php echo e(route('conductores.export', request()->query())); ?>">
                            <i class="fas fa-file-csv"></i> Exportar CSV
                        </a>
                        <a class="dropdown-item" href="<?php echo e(route('conductores.plantilla')); ?>">
                            <i class="fas fa-file-download"></i> Descargar Plantilla
                        </a>
                    </div>
                </div>
            </div>

            
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Conductores
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo e($metricas['total'] ?? 0); ?>

                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Conductores Activos
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo e($metricas['conductores_activos'] ?? 0); ?>

                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Puntualidad Promedio
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo e($metricas['puntualidad_promedio'] ?? 0); ?>%
                                    </div>
                                    <div class="progress progress-sm mr-2">
                                        <div class="progress-bar bg-info" style="width: <?php echo e($metricas['puntualidad_promedio'] ?? 0); ?>%"></div>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Conductores Críticos
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo e($metricas['conductores_criticos'] ?? 0); ?>

                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertas del Sistema -->
            <?php if(($metricas['conductores_criticos'] ?? 0) > 0): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Atención:</strong> Hay <?php echo e($metricas['conductores_criticos']); ?> conductor(es) con 6 o más días trabajados que requieren descanso.
                <a href="<?php echo e(route('conductores.index', ['filtro' => 'criticos'])); ?>" class="alert-link">Ver conductores críticos</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-filter"></i> Filtros de Búsqueda
                            </h6>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">
                                Mostrando <?php echo e($conductores->firstItem() ?? 0); ?> - <?php echo e($conductores->lastItem() ?? 0); ?>

                                de <?php echo e($conductores->total() ?? 0); ?> conductores
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form action="<?php echo e(route('conductores.index')); ?>" method="GET">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="buscar" class="form-label">Buscar</label>
                                <input type="text" name="buscar" class="form-control"
                                       placeholder="Código, nombre, DNI o licencia"
                                       value="<?php echo e(request('buscar')); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="estado" class="form-label">Estado</label>
                                <select name="estado" class="form-select">
                                    <option value="">Todos los estados</option>
                                    <option value="DISPONIBLE" <?php echo e(request('estado') == 'DISPONIBLE' ? 'selected' : ''); ?>>Disponible</option>
                                    <option value="DESCANSO_FISICO" <?php echo e(request('estado') == 'DESCANSO_FISICO' ? 'selected' : ''); ?>>Descanso Físico</option>
                                    <option value="DESCANSO_SEMANAL" <?php echo e(request('estado') == 'DESCANSO_SEMANAL' ? 'selected' : ''); ?>>Descanso Semanal</option>
                                    <option value="VACACIONES" <?php echo e(request('estado') == 'VACACIONES' ? 'selected' : ''); ?>>Vacaciones</option>
                                    <option value="SUSPENDIDO" <?php echo e(request('estado') == 'SUSPENDIDO' ? 'selected' : ''); ?>>Suspendido</option>
                                    <option value="FALTO_OPERATIVO" <?php echo e(request('estado') == 'FALTO_OPERATIVO' ? 'selected' : ''); ?>>Falta Operativa</option>
                                    <option value="FALTO_NO_OPERATIVO" <?php echo e(request('estado') == 'FALTO_NO_OPERATIVO' ? 'selected' : ''); ?>>Falta No Operativa</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="subempresa" class="form-label">Subempresa</label>
                                <select name="subempresa" class="form-select">
                                    <option value="">Todas las subempresas</option>
                                    <?php $__currentLoopData = $subempresas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subempresa): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($subempresa); ?>" <?php echo e(request('subempresa') == $subempresa ? 'selected' : ''); ?>>
                                            <?php echo e($subempresa); ?>

                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="filtro" class="form-label">Filtro Especial</label>
                                <select name="filtro" class="form-select">
                                    <option value="">Sin filtro</option>
                                    <option value="criticos" <?php echo e(request('filtro') == 'criticos' ? 'selected' : ''); ?>>Críticos (6+ días)</option>
                                    <option value="disponibles" <?php echo e(request('filtro') == 'disponibles' ? 'selected' : ''); ?>>Solo Disponibles</option>
                                    <option value="descanso" <?php echo e(request('filtro') == 'descanso' ? 'selected' : ''); ?>>En Descanso</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="dias_acumulados" class="form-label">Días Trabajados</label>
                                <select name="dias_acumulados" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="normales" <?php echo e(request('dias_acumulados') == 'normales' ? 'selected' : ''); ?>>
                                        Normales (0-5 días)
                                    </option>
                                    <option value="criticos" <?php echo e(request('dias_acumulados') == 'criticos' ? 'selected' : ''); ?>>
                                        Críticos (6+ días)
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i>
                                </button>
                                <a href="<?php echo e(route('conductores.index')); ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Lista de Conductores (<?php echo e($conductores->total()); ?> registros)
                        <?php if(request()->hasAny(['buscar', 'estado', 'subempresa', 'filtro'])): ?>
                            <small class="text-muted">- Filtrado</small>
                        <?php endif; ?>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped" width="100%" cellspacing="0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre Completo</th>
                                    <th>DNI</th>
                                    <th>Estado</th>
                                    <th>Origen</th>
                                    <th>Días Acumulados</th>
                                    <th>Puntualidad</th>
                                    <th>Eficiencia</th>
                                    <th width="120px">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__empty_1 = true; $__currentLoopData = $conductores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $conductor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr class="<?php echo e($conductor->dias_acumulados >= 6 ? 'table-warning' : ''); ?>">
                                        <td>
                                            <strong><?php echo e($conductor->codigo_conductor ?? $conductor->codigo ?? 'N/A'); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo e($conductor->nombre); ?> <?php echo e($conductor->apellido); ?>

                                            <?php if($conductor->telefono): ?>
                                                <br><small class="text-muted"><?php echo e($conductor->telefono); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo e($conductor->dni); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo e($conductor->estado == 'DISPONIBLE' ? 'success' :
                                                ($conductor->estado == 'DESCANSO_FISICO' || $conductor->estado == 'DESCANSO_SEMANAL' ? 'primary' :
                                                ($conductor->estado == 'VACACIONES' ? 'info' :
                                                ($conductor->estado == 'SUSPENDIDO' ? 'danger' : 'warning')))); ?>">
                                                <?php echo e(str_replace('_', ' ', $conductor->estado)); ?>

                                            </span>
                                        </td>
                                        <td><?php echo e($conductor->subempresa ?? $conductor->origen_conductor ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo e($conductor->dias_acumulados >= 6 ? 'danger' : ($conductor->dias_acumulados >= 4 ? 'warning' : 'success')); ?>">
                                                <?php echo e($conductor->dias_acumulados); ?> días
                                            </span>
                                            <?php if($conductor->dias_acumulados >= 6): ?>
                                                <i class="fas fa-exclamation-triangle text-danger" title="Requiere descanso"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><?php echo e($conductor->puntualidad ?? 0); ?>%</span>
                                                <div class="progress" style="width: 50px; height: 8px;">
                                                    <div class="progress-bar bg-<?php echo e(($conductor->puntualidad ?? 0) >= 85 ? 'success' : (($conductor->puntualidad ?? 0) >= 70 ? 'warning' : 'danger')); ?>"
                                                         style="width: <?php echo e($conductor->puntualidad ?? 0); ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><?php echo e($conductor->eficiencia ?? 0); ?>%</span>
                                                <div class="progress" style="width: 50px; height: 8px;">
                                                    <div class="progress-bar bg-<?php echo e(($conductor->eficiencia ?? 0) >= 80 ? 'success' : (($conductor->eficiencia ?? 0) >= 60 ? 'warning' : 'danger')); ?>"
                                                         style="width: <?php echo e($conductor->eficiencia ?? 0); ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="<?php echo e(route('conductores.show', $conductor)); ?>"
                                                   class="btn btn-info btn-sm"
                                                   title="Ver Detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo e(route('conductores.edit', $conductor)); ?>"
                                                   class="btn btn-warning btn-sm"
                                                   title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if($conductor->dias_acumulados >= 6 && $conductor->estado == 'DISPONIBLE'): ?>
                                                    <button class="btn btn-secondary btn-sm"
                                                            onclick="enviarADescanso(<?php echo e($conductor->id); ?>)"
                                                            title="Enviar a Descanso">
                                                        <i class="fas fa-bed"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-danger btn-sm"
                                                        onclick="confirmarEliminacion(<?php echo e($conductor->id); ?>)"
                                                        title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No se encontraron conductores</h5>
                                            <p class="text-muted">
                                                <?php if(request()->hasAny(['buscar', 'estado', 'subempresa', 'filtro'])): ?>
                                                    Intenta ajustar los filtros de búsqueda o
                                                    <a href="<?php echo e(route('conductores.index')); ?>">limpiar filtros</a>
                                                <?php else: ?>
                                                    <a href="<?php echo e(route('conductores.create')); ?>">Crear el primer conductor</a>
                                                <?php endif; ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <?php echo e($conductores->appends(request()->input())->links()); ?>

                </div>
            </div>

            
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-upload"></i> Importar Conductores
                    </h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo e(route('conductores.importar')); ?>" method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="archivo" class="form-label">Archivo de Conductores</label>
                                <input type="file" class="form-control" name="archivo" accept=".xlsx,.xls,.csv" required>
                                <small class="form-text text-muted">
                                    Formatos soportados: Excel (.xlsx, .xls) y CSV. Máximo 10MB.
                                </small>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-success me-2">
                                    <i class="fas fa-upload"></i> Importar
                                </button>
                                <a href="<?php echo e(route('conductores.plantilla')); ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-download"></i> Descargar Plantilla
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-chart-pie"></i> Distribución por Estado
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if(isset($estadisticas)): ?>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="border-right">
                                            <strong class="text-success"><?php echo e($estadisticas['disponibles'] ?? 0); ?></strong>
                                            <br><small>Disponibles</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border-right">
                                            <strong class="text-primary"><?php echo e($estadisticas['en_descanso'] ?? 0); ?></strong>
                                            <br><small>En Descanso</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <strong class="text-danger"><?php echo e($estadisticas['criticos'] ?? 0); ?></strong>
                                        <br><small>Críticos</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-tachometer-alt"></i> Promedios Generales
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-right">
                                        <strong class="text-info"><?php echo e($metricas['puntualidad_promedio'] ?? 0); ?>%</strong>
                                        <br><small>Puntualidad</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <strong class="text-success"><?php echo e($metricas['eficiencia_promedio'] ?? 0); ?>%</strong>
                                    <br><small>Eficiencia</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('styles'); ?>
<style>
.border-left-primary {
    border-left: .25rem solid #4e73df!important;
}

.border-left-success {
    border-left: .25rem solid #1cc88a!important;
}

.border-left-info {
    border-left: .25rem solid #36b9cc!important;
}

.border-left-warning {
    border-left: .25rem solid #f6c23e!important;
}

.border-left-danger {
    border-left: .25rem solid #dc3545!important;
}

.progress-sm {
    height: .5rem;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

.badge {
    font-size: 0.75em;
}

.btn-group-sm > .btn, .btn-sm {
    padding: .25rem .5rem;
    font-size: .875rem;
    border-radius: .2rem;
}

.card-metric {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
}

.card-metric.green {
    background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
}

.card-metric.red {
    background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
}

.card-metric.yellow {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.border-right {
    border-right: 1px solid #dee2e6;
}

/* Fix for empty content */
.card-body {
    background-color: #fff !important;
    color: #5a5c69 !important;
}

.card-title, .card-text {
    color: inherit !important;
}
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
function confirmarEliminacion(conductorId) {
    if (confirm('¿Estás seguro de que deseas eliminar este conductor? Esta acción no se puede deshacer.')) {
        // Crear formulario dinámico para DELETE
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/conductores/${conductorId}`;

        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_token';
        tokenInput.value = '<?php echo e(csrf_token()); ?>';

        form.appendChild(methodInput);
        form.appendChild(tokenInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function enviarADescanso(conductorId) {
    if (confirm('¿Deseas enviar este conductor a descanso automáticamente?')) {
        fetch(`/conductores/${conductorId}/enviar-descanso`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
            },
            body: JSON.stringify({
                tipo_descanso: 'FISICO',
                motivo: 'Enviado a descanso por días acumulados críticos'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Conductor enviado a descanso exitosamente');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    }
}

// Auto-actualizar métricas cada 30 segundos
setInterval(() => {
    // Aquí podrías implementar una actualización AJAX de las métricas
    // sin recargar toda la página
    console.log('Auto-refresh check...');
}, 30000);

// Funcionalidad de búsqueda en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="buscar"]');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                // Auto-submit después de 1 segundo de inactividad
                // document.querySelector('form').submit();
            }, 1000);
        });
    }
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\sipat-laravel\resources\views/conductores/index.blade.php ENDPATH**/ ?>
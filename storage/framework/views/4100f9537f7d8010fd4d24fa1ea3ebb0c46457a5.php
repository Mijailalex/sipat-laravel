<?php $__env->startSection('title', 'Plantillas - SIPAT'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-file-alt"></i> Gestión de Plantillas
    </h1>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaPlantilla">
            <i class="fas fa-plus"></i> Nueva Plantilla
        </button>
    </div>
</div>

<!-- Métricas -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Plantillas</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo e($metricas['total']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-alt fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Activas</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo e($metricas['activas']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Turnos</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo e($metricas['turnos_total']); ?></div>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Última Generación</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800"><?php echo e($metricas['ultima_generacion']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de plantillas -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Plantillas Disponibles</h6>
    </div>
    <div class="card-body">
        <?php if($plantillas->count() > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Total Turnos</th>
                            <th>Estado</th>
                            <th>Creada</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $plantillas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plantilla): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td><strong><?php echo e($plantilla->nombre); ?></strong></td>
                                <td><?php echo e($plantilla->descripcion ?? 'Sin descripción'); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo e($plantilla->total_turnos ?? $plantilla->turnos->count()); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo e($plantilla->activa ? 'success' : 'secondary'); ?>">
                                        <?php echo e($plantilla->activa ? 'Activa' : 'Inactiva'); ?>

                                    </span>
                                </td>
                                <td><?php echo e($plantilla->created_at->format('d/m/Y H:i')); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo e(route('plantillas.show', $plantilla)); ?>"
                                           class="btn btn-info" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo e(route('plantillas.pdf', $plantilla->id)); ?>"
                                           class="btn btn-danger" title="Descargar PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="<?php echo e(route('plantillas.excel', $plantilla->id)); ?>"
                                           class="btn btn-success" title="Descargar Excel">
                                            <i class="fas fa-file-excel"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                <?php echo e($plantillas->links()); ?>

            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                <h5>No hay plantillas creadas</h5>
                <p class="text-muted">Crea tu primera plantilla subiendo un archivo Excel.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaPlantilla">
                    <i class="fas fa-plus"></i> Crear Primera Plantilla
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nueva Plantilla -->
<div class="modal fade" id="modalNuevaPlantilla" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?php echo e(route('plantillas.store')); ?>" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" name="nombre" required
                               placeholder="Ej: Plantilla Semana 28-2025">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"
                                  placeholder="Descripción de la plantilla..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Archivo Excel/CSV *</label>
                        <input type="file" class="form-control" name="archivo_excel"
                               accept=".xlsx,.xls,.csv" required>
                        <div class="form-text">
                            El archivo debe contener las columnas: Fecha de Salida, Número de Salida,
                            Hora Salida, Hora Llegada, Código de Bus, Código de Conductor,
                            Nombre de Conductor, Tipo de Servicio, Origen-Destino, Origen de Conductor
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Plantilla</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\sipat-laravel\resources\views/plantillas/index.blade.php ENDPATH**/ ?>
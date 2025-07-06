<?php $__env->startSection('title', 'Validaciones - SIPAT'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-check-circle text-success"></i>
        Sistema de Validaciones
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" onclick="ejecutarValidaciones()">
            <i class="fas fa-play"></i> Ejecutar Validaciones
        </button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center bg-primary text-white">
            <div class="card-body">
                <h4><?php echo e($metricas['total']); ?></h4>
                <small>Total</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-warning text-white">
            <div class="card-body">
                <h4><?php echo e($metricas['pendientes']); ?></h4>
                <small>Pendientes</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-danger text-white">
            <div class="card-body">
                <h4><?php echo e($metricas['criticas']); ?></h4>
                <small>Críticas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-success text-white">
            <div class="card-body">
                <h4><?php echo e($metricas['resueltas_hoy']); ?></h4>
                <small>Resueltas Hoy</small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if($validaciones->count() > 0): ?>
            <?php $__currentLoopData = $validaciones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $validacion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="alert alert-<?php echo e($validacion->severidad == 'CRITICA' ? 'danger' : 'warning'); ?>">
                    <strong><?php echo e($validacion->tipo); ?></strong><br>
                    <?php echo e($validacion->mensaje); ?><br>
                    <small>Conductor: <?php echo e($validacion->conductor->nombre ?? 'N/A'); ?></small>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5>No hay validaciones pendientes</h5>
                <button class="btn btn-primary" onclick="ejecutarValidaciones()">
                    <i class="fas fa-play"></i> Ejecutar Validaciones
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
function ejecutarValidaciones() {
    fetch('<?php echo e(route("validaciones.ejecutar")); ?>', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        location.reload();
    });
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\sipat-laravel\resources\views/validaciones/index.blade.php ENDPATH**/ ?>
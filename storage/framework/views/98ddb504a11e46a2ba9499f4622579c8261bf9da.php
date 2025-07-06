<?php $__env->startSection('content'); ?>
<h1>Centro de Reportes</h1>

<div class="row">
    <div class="col-md-3">
        <div class="card text-center bg-primary text-white">
            <div class="card-body">
                <h4><?php echo e($metricas['conductores_total']); ?></h4>
                <small>Conductores</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-success text-white">
            <div class="card-body">
                <h4><?php echo e($metricas['conductores_activos']); ?></h4>
                <small>Activos</small>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\sipat-laravel\resources\views/reportes/index.blade.php ENDPATH**/ ?>
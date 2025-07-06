<?php $__env->startSection('content'); ?>
<div class="container">
    <h1>Parámetros Predictivos</h1>

    <a href="<?php echo e(route('parametros_predictivos.create')); ?>" class="btn btn-primary">
        Crear Nuevo Parámetro
    </a>

    <table class="table">
        <thead>
            <tr>
                <th>Clave</th>
                <th>Tipo</th>
                <th>Activo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $parametros; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parametro): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e($parametro->clave); ?></td>
                <td><?php echo e($parametro->tipo_prediccion); ?></td>
                <td><?php echo e($parametro->activo ? 'Sí' : 'No'); ?></td>
                <td>
                    <a href="#" class="btn btn-sm btn-info">Ver</a>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\sipat-laravel\resources\views/parametros/index.blade.php ENDPATH**/ ?>
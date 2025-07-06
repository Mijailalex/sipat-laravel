<?php $__env->startSection('title', 'Dashboard - SIPAT'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tachometer-alt text-primary"></i>
        Dashboard Principal
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="actualizarDatos()">
                <i class="fas fa-sync-alt"></i> Actualizar
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-download"></i> Exportar
            </button>
        </div>
    </div>
</div>

<!-- Métricas Principales -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Cobertura de Turnos
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo e(number_format($metricas['cobertura_turnos'], 1)); ?>%
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
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
                            <?php echo e($metricas['conductores_activos']); ?>

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
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Puntualidad Promedio
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo e($metricas['puntualidad_promedio']); ?>%
                        </div>
                        <div class="progress progress-sm mr-2">
                            <div class="progress-bar bg-info" style="width: <?php echo e($metricas['puntualidad_promedio']); ?>%"></div>
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
                            Validaciones Pendientes
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo e($metricas['validaciones_pendientes']); ?>

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

<!-- Gráficos -->
<div class="row">
    <!-- Gráfico de Conductores por Estado -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Evolución Semanal</h6>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="tendenciasChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico Circular -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Estados de Conductores</h6>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="estadosChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tablas de Información -->
<div class="row">
    <!-- Conductores Destacados -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-star text-warning"></i>
                    Conductores Destacados
                </h6>
            </div>
            <div class="card-body">
                <?php if($conductoresDestacados->count() > 0): ?>
                    <?php $__currentLoopData = $conductoresDestacados; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $conductor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="mr-3">
                                <?php if($index == 0): ?>
                                    <div class="icon-circle bg-warning">
                                        <i class="fas fa-trophy text-white"></i>
                                    </div>
                                <?php elseif($index == 1): ?>
                                    <div class="icon-circle bg-secondary">
                                        <i class="fas fa-medal text-white"></i>
                                    </div>
                                <?php elseif($index == 2): ?>
                                    <div class="icon-circle bg-danger">
                                        <i class="fas fa-award text-white"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="icon-circle bg-primary">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="small font-weight-bold"><?php echo e($conductor->nombre); ?></div>
                                <div class="small text-gray-500"><?php echo e($conductor->codigo); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="font-weight-bold text-primary">
                                    <?php echo e(number_format($conductor->score_general, 1)); ?>

                                </div>
                                <div class="small text-gray-500">Score</div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php else: ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Validaciones Recientes -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-bell text-warning"></i>
                    Validaciones Pendientes
                </h6>
            </div>
            <div class="card-body">
                <?php if($validacionesPendientes->count() > 0): ?>
                    <?php $__currentLoopData = $validacionesPendientes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $validacion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="mr-3">
                                <?php if($validacion->severidad === 'CRITICA'): ?>
                                    <i class="fas fa-exclamation-circle text-danger"></i>
                                <?php elseif($validacion->severidad === 'ADVERTENCIA'): ?>
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                <?php else: ?>
                                    <i class="fas fa-info-circle text-info"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="small font-weight-bold"><?php echo e($validacion->tipo); ?></div>
                                <div class="small text-gray-500">
                                    <?php echo e($validacion->conductor->nombre); ?> (<?php echo e($validacion->conductor->codigo); ?>)
                                </div>
                                <div class="small text-gray-600"><?php echo e(Str::limit($validacion->mensaje, 50)); ?></div>
                            </div>
                            <div class="text-right">
                                <span class="badge badge-<?php echo e($validacion->severidad === 'CRITICA' ? 'danger' : ($validacion->severidad === 'ADVERTENCIA' ? 'warning' : 'info')); ?>">
                                    <?php echo e($validacion->severidad); ?>

                                </span>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <div class="text-center">
                        <a href="<?php echo e(route('validaciones.index')); ?>" class="btn btn-sm btn-primary">
                            Ver todas las validaciones
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No hay validaciones pendientes</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Alertas del Sistema -->
<?php if($conductoresCriticos > 0): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Atención:</strong> Hay <?php echo e($conductoresCriticos); ?> conductor(es) con 6 o más días trabajados que requieren descanso.
    <a href="<?php echo e(route('conductores.index', ['dias_acumulados' => 'criticos'])); ?>" class="alert-link">Ver conductores críticos</a>
    <button type="button" class="close" data-dismiss="alert">
        <span>&times;</span>
    </button>
</div>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('styles'); ?>
<style>
.icon-circle {
    height: 2.5rem;
    width: 2.5rem;
    border-radius: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

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

.progress-sm {
    height: .5rem;
}
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
// Datos para los gráficos
const conductoresPorEstado = <?php echo json_encode($conductoresPorEstado, 15, 512) ?>;
const tendenciasSemanales = <?php echo json_encode($tendenciasSemanales, 15, 512) ?>;

// Gráfico de tendencias semanales
const ctx1 = document.getElementById('tendenciasChart').getContext('2d');
const tendenciasChart = new Chart(ctx1, {
    type: 'line',
    data: {
        labels: tendenciasSemanales.dias,
        datasets: [{
            label: 'Cumplimiento %',
            data: tendenciasSemanales.cumplimiento,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1
        }, {
            label: 'Eficiencia %',
            data: tendenciasSemanales.eficiencia,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: false,
                min: 85,
                max: 100
            }
        }
    }
});

// Gráfico circular de estados
const ctx2 = document.getElementById('estadosChart').getContext('2d');
const estadosChart = new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: Object.keys(conductoresPorEstado),
        datasets: [{
            data: Object.values(conductoresPorEstado),
            backgroundColor: [
                '#28a745',
                '#007bff',
                '#6f42c1',
                '#dc3545'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Función para actualizar datos
function actualizarDatos() {
    // Mostrar indicador de carga
    Swal.fire({
        title: 'Actualizando datos...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Simular actualización
    setTimeout(() => {
        location.reload();
    }, 2000);
}

// Auto-actualizar cada 5 minutos
setInterval(function() {
    fetch('<?php echo e(route("dashboard.chart-data")); ?>')
        .then(response => response.json())
        .then(data => {
            // Actualizar gráficos con nuevos datos
            console.log('Datos actualizados:', data);
        })
        .catch(error => console.error('Error:', error));
}, 300000); // 5 minutos
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\sipat-laravel\resources\views/dashboard/index.blade.php ENDPATH**/ ?>
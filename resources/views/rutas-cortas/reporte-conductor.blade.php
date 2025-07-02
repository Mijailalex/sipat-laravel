@extends('layouts.app')

@section('title', 'Reporte de Rutas Cortas: ' . $conductor->nombre . ' - SIPAT')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line text-info"></i>
        Reporte de Rutas Cortas
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary" onclick="cambiarSemana(-1)">
                <i class="fas fa-chevron-left"></i> Anterior
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="cambiarSemana(1)">
                Siguiente <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" onclick="exportarReporte()">
                <i class="fas fa-download"></i> Exportar
            </button>
        </div>
        <a href="{{ route('rutas-cortas.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<!-- Header del Conductor -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-1">{{ $conductor->nombre }}</h4>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-primary">{{ $conductor->codigo }}</span>
                    <span class="badge bg-info">{{ $conductor->origen }}</span>
                    <span class="badge bg-{{ $conductor->estado == 'DISPONIBLE' ? 'success' : 'warning' }}">
                        {{ $conductor->estado }}
                    </span>
                </div>
                <small class="text-muted">
                    <i class="fas fa-calendar"></i> Reporte de la Semana {{ $semana }} del {{ $año }}
                    @if($conductor->ultima_ruta_corta)
                        • Última ruta corta: {{ $conductor->ultima_ruta_corta->format('d/m/Y') }}
                    @endif
                </small>
            </div>
            <div class="col-md-4 text-end">
                <div class="row text-center">
                    <div class="col-6">
                        <h5 class="text-primary mb-0">{{ $conductor->puntualidad }}%</h5>
                        <small>Puntualidad</small>
                    </div>
                    <div class="col-6">
                        <h5 class="text-success mb-0">{{ $conductor->eficiencia }}%</h5>
                        <small>Eficiencia</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Balance Semanal -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-balance-scale"></i> Balance Semanal
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h4 class="text-primary">{{ $balance['programadas'] }}</h4>
                            <small>Programadas</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h4 class="text-success">{{ $balance['completadas'] }}</h4>
                            <small>Completadas</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h4 class="text-info">{{ $balance['total'] }}</h4>
                            <small>Total</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 mb-2">
                            <h4 class="text-warning">{{ $balance['canceladas'] ?? 0 }}</h4>
                            <small>Canceladas</small>
                        </div>
                    </div>
                </div>

                <div class="progress mt-3 mb-2" style="height: 10px;">
                    <div class="progress-bar {{ $balance['objetivo_cumplido'] ? 'bg-success' : 'bg-warning' }}"
                         style="width: {{ min($balance['total'] / 4 * 100, 100) }}%"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-{{ $balance['objetivo_cumplido'] ? 'success' : 'muted' }}">
                        {{ $balance['objetivo_cumplido'] ? '✓ Objetivo cumplido' : 'Objetivo: 3-4 rutas' }}
                    </small>
                    <small><strong>{{ $balance['porcentaje_cumplimiento'] }}%</strong></small>
                </div>

                <hr>

                <div class="text-center">
                    <h5 class="text-success mb-0">S/ {{ number_format($balance['total_ingresos'], 2) }}</h5>
                    <small class="text-muted">Ingresos de la semana</small>
                </div>
            </div>
        </div>

        <!-- Estadísticas Adicionales -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-chart-pie"></i> Estadísticas
                </h6>
            </div>
            <div class="card-body">
                @if($rutas->count() > 0)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Duración promedio:</span>
                            <strong>{{ round($rutas->avg('duracion_horas'), 1) }}h</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Ingreso promedio:</span>
                            <strong>S/ {{ number_format($rutas->avg('ingreso_estimado'), 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Tramos únicos:</span>
                            <strong>{{ $rutas->pluck('tramo')->unique()->count() }}</strong>
                        </div>
                    </div>

                    <!-- Distribución por rumbo -->
                    <div class="mb-3">
                        <h6 class="small mb-2">Distribución por Rumbo:</h6>
                        @php
                            $rumboSur = $rutas->where('rumbo', 'SUR')->count();
                            $rumboNorte = $rutas->where('rumbo', 'NORTE')->count();
                            $total = $rutas->count();
                        @endphp

                        <div class="progress mb-1" style="height: 20px;">
                            @if($rumboSur > 0)
                                <div class="progress-bar bg-primary" style="width: {{ ($rumboSur / $total) * 100 }}%">
                                    SUR ({{ $rumboSur }})
                                </div>
                            @endif
                            @if($rumboNorte > 0)
                                <div class="progress-bar bg-info" style="width: {{ ($rumboNorte / $total) * 100 }}%">
                                    NORTE ({{ $rumboNorte }})
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-center text-muted">
                        <i class="fas fa-info-circle"></i>
                        <br>
                        No hay rutas en esta semana
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Rutas Detalladas -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-list"></i> Rutas de la Semana
                </h6>
            </div>
            <div class="card-body">
                @if($rutas->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tramo</th>
                                    <th>Horario</th>
                                    <th>Estado</th>
                                    <th>Ingreso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rutas as $ruta)
                                    <tr class="{{ $ruta->es_consecutiva ? 'table-warning' : '' }}">
                                        <td>
                                            <strong>{{ $ruta->fecha_asignacion->format('d/m') }}</strong>
                                            <br>
                                            <small class="text-muted">
                                                {{ ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][$ruta->dia_semana] }}
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $ruta->rumbo == 'SUR' ? 'primary' : 'info' }}">
                                                {{ $ruta->rumbo }}
                                            </span>
                                            <br>
                                            <strong>{{ $ruta->tramo }}</strong>
                                            @if($ruta->es_consecutiva)
                                                <br>
                                                <small class="text-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> Consecutiva
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <i class="fas fa-clock"></i>
                                            {{ $ruta->hora_inicio->format('H:i') }} - {{ $ruta->hora_fin->format('H:i') }}
                                            <br>
                                            <small class="text-muted">{{ $ruta->duracion_horas }}h</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $ruta->estado_color }}">
                                                {{ $ruta->estado }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong>S/ {{ number_format($ruta->ingreso_estimado, 2) }}</strong>
                                        </td>
                                        <td>
                                            <a href="{{ route('rutas-cortas.show', $ruta) }}"
                                               class="btn btn-outline-info btn-sm" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="4">Totales:</th>
                                    <th>S/ {{ number_format($rutas->sum('ingreso_estimado'), 2) }}</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-route fa-3x text-muted mb-3"></i>
                        <h5>No hay rutas asignadas</h5>
                        <p class="text-muted">No se encontraron rutas cortas para esta semana.</p>
                        <a href="{{ route('rutas-cortas.create') }}?conductor_id={{ $conductor->id }}"
                           class="btn btn-primary">
                            <i class="fas fa-plus"></i> Asignar Ruta
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Histórico de las Últimas Semanas -->
@if($historico->count() > 0)
<div class="card">
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="fas fa-history"></i> Histórico de las Últimas 4 Semanas
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            @foreach($historico as $index => $semanaHistorica)
                <div class="col-md-3">
                    <div class="card {{ $semanaHistorica['semana'] == $semana ? 'border-primary' : 'border-light' }}">
                        <div class="card-body text-center">
                            <h6 class="card-title">
                                Semana {{ $semanaHistorica['semana'] }}
                                @if($semanaHistorica['semana'] == $semana)
                                    <span class="badge bg-primary">Actual</span>
                                @endif
                            </h6>

                            <div class="row">
                                <div class="col-6">
                                    <h5 class="text-primary">{{ $semanaHistorica['total'] }}</h5>
                                    <small>Rutas</small>
                                </div>
                                <div class="col-6">
                                    <h5 class="text-success">{{ $semanaHistorica['completadas'] }}</h5>
                                    <small>Completadas</small>
                                </div>
                            </div>

                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar {{ $semanaHistorica['objetivo_cumplido'] ? 'bg-success' : 'bg-warning' }}"
                                     style="width: {{ min($semanaHistorica['total'] / 4 * 100, 100) }}%"></div>
                            </div>

                            <small class="text-{{ $semanaHistorica['objetivo_cumplido'] ? 'success' : 'muted' }}">
                                {{ $semanaHistorica['objetivo_cumplido'] ? '✓ Cumplido' : 'Pendiente' }}
                            </small>

                            <hr class="my-2">

                            <strong class="text-success">
                                S/ {{ number_format($semanaHistorica['total_ingresos'], 0) }}
                            </strong>
                            <br>
                            <small class="text-muted">Ingresos</small>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Gráfico de tendencias -->
        <div class="mt-4">
            <h6>Tendencia de Rutas Completadas</h6>
            <canvas id="tendenciasChart" height="100"></canvas>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
// Variables globales
let semanaActual = {{ $semana }};
let añoActual = {{ $año }};
const conductorId = {{ $conductor->id }};

// Gráfico de tendencias
@if($historico->count() > 0)
const ctx = document.getElementById('tendenciasChart').getContext('2d');
const tendenciasChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: @json($historico->pluck('semana')->map(fn($s) => "S{$s}")),
        datasets: [{
            label: 'Rutas Completadas',
            data: @json($historico->pluck('completadas')),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1,
            fill: true
        }, {
            label: 'Total Rutas',
            data: @json($historico->pluck('total')),
            borderColor: 'rgb(54, 162, 235)',
            backgroundColor: 'rgba(54, 162, 235, 0.1)',
            tension: 0.1,
            borderDash: [5, 5]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 6,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
@endif

function cambiarSemana(direccion) {
    let nuevaSemana = semanaActual + direccion;
    let nuevoAño = añoActual;

    if (nuevaSemana <= 0) {
        nuevaSemana = 52;
        nuevoAño--;
    } else if (nuevaSemana > 52) {
        nuevaSemana = 1;
        nuevoAño++;
    }

    const url = new URL(window.location);
    url.searchParams.set('semana', nuevaSemana);
    url.searchParams.set('año', nuevoAño);

    window.location.href = url.toString();
}

function exportarReporte() {
    Swal.fire({
        title: 'Generando reporte...',
        text: 'Se está preparando el reporte del conductor.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Simular generación de reporte
    setTimeout(() => {
        Swal.fire({
            icon: 'success',
            title: 'Reporte generado',
            text: 'El reporte está listo para descargar.',
            showCancelButton: true,
            confirmButtonText: 'Descargar',
            cancelButtonText: 'Cerrar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Crear CSV con los datos del reporte
                let csvContent = "Reporte de Rutas Cortas\n";
                csvContent += "Conductor,{{ $conductor->nombre }}\n";
                csvContent += "Código,{{ $conductor->codigo }}\n";
                csvContent += "Semana,{{ $semana }}\n";
                csvContent += "Año,{{ $año }}\n\n";

                csvContent += "BALANCE SEMANAL\n";
                csvContent += "Programadas,{{ $balance['programadas'] }}\n";
                csvContent += "Completadas,{{ $balance['completadas'] }}\n";
                csvContent += "Total,{{ $balance['total'] }}\n";
                csvContent += "Ingresos,S/ {{ number_format($balance['total_ingresos'], 2) }}\n";
                csvContent += "Objetivo Cumplido,{{ $balance['objetivo_cumplido'] ? 'SÍ' : 'NO' }}\n\n";

                csvContent += "DETALLE DE RUTAS\n";
                csvContent += "Fecha,Tramo,Rumbo,Horario,Estado,Ingreso\n";

                @foreach($rutas as $ruta)
                csvContent += "{{ $ruta->fecha_asignacion->format('d/m/Y') }},{{ $ruta->tramo }},{{ $ruta->rumbo }},{{ $ruta->hora_inicio->format('H:i') }}-{{ $ruta->hora_fin->format('H:i') }},{{ $ruta->estado }},S/ {{ number_format($ruta->ingreso_estimado, 2) }}\n";
                @endforeach

                // Descargar archivo
                const link = document.createElement('a');
                link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvContent);
                link.download = `reporte_rutas_cortas_{{ $conductor->codigo }}_S{{ $semana }}_{{ $año }}.csv`;
                link.click();
            }
        });
    }, 2000);
}

// Auto-actualizar datos cada 5 minutos
setInterval(function() {
    fetch(`{{ route("api.rutas-cortas.balance-semanal") }}?conductor_id=${conductorId}&semanas=1`)
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                const balance = data[0];
                // Actualizar métricas sin recargar la página
                console.log('Balance actualizado:', balance);
            }
        })
        .catch(error => console.log('Error actualizando balance:', error));
}, 300000); // 5 minutos
</script>
@endsection

@extends('layouts.app')

@section('title', 'Reportes Avanzados - SIPAT')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-chart-bar"></i> Reportes Avanzados
    </h1>
</div>

<div class="row">
    <!-- Reporte de Conductores -->
    <div class="col-lg-6 mb-4">
        <div class="card border-left-primary shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <h5 class="font-weight-bold text-primary mb-3">
                            <i class="fas fa-users"></i> Análisis de Conductores
                        </h5>
                        <p class="text-gray-600 mb-3">
                            Reporte detallado de rendimiento, estados y métricas de conductores
                        </p>
                        <a href="{{ route('reportes-avanzados.conductores') }}" class="btn btn-primary">
                            <i class="fas fa-chart-line"></i> Ver Reporte
                        </a>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reporte de Validaciones -->
    <div class="col-lg-6 mb-4">
        <div class="card border-left-success shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <h5 class="font-weight-bold text-success mb-3">
                            <i class="fas fa-check-circle"></i> Análisis de Validaciones
                        </h5>
                        <p class="text-gray-600 mb-3">
                            Tendencias, tipos y tiempos de resolución de validaciones
                        </p>
                        <a href="{{ route('reportes-avanzados.validaciones') }}" class="btn btn-success">
                            <i class="fas fa-analytics"></i> Ver Análisis
                        </a>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reporte de Eficiencia -->
    <div class="col-lg-6 mb-4">
        <div class="card border-left-info shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <h5 class="font-weight-bold text-info mb-3">
                            <i class="fas fa-tachometer-alt"></i> Eficiencia Operacional
                        </h5>
                        <p class="text-gray-600 mb-3">
                            KPIs, tendencias y métricas de rendimiento del sistema
                        </p>
                        <a href="{{ route('reportes-avanzados.eficiencia') }}" class="btn btn-info">
                            <i class="fas fa-chart-pie"></i> Ver KPIs
                        </a>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tachometer-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reporte de Auditoría -->
    <div class="col-lg-6 mb-4">
        <div class="card border-left-warning shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <h5 class="font-weight-bold text-warning mb-3">
                            <i class="fas fa-history"></i> Auditoría de Actividad
                        </h5>
                        <p class="text-gray-600 mb-3">
                            Logs de actividad, cambios y trazabilidad del sistema
                        </p>
                        <a href="{{ route('reportes-avanzados.auditoria') }}" class="btn btn-warning">
                            <i class="fas fa-search"></i> Ver Auditoría
                        </a>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-history fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Accesos Rápidos -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-download"></i> Exportaciones Rápidas
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="{{ route('reportes-avanzados.conductores', ['formato' => 'pdf']) }}"
                           class="btn btn-outline-primary btn-block">
                            <i class="fas fa-file-pdf"></i> Conductores PDF
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('reportes-avanzados.validaciones', ['formato' => 'excel']) }}"
                           class="btn btn-outline-success btn-block">
                            <i class="fas fa-file-excel"></i> Validaciones Excel
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('reportes-avanzados.eficiencia', ['formato' => 'pdf']) }}"
                           class="btn btn-outline-info btn-block">
                            <i class="fas fa-file-pdf"></i> KPIs PDF
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('reportes-avanzados.auditoria', ['formato' => 'csv']) }}"
                           class="btn btn-outline-warning btn-block">
                            <i class="fas fa-file-csv"></i> Auditoría CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

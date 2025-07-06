<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <title><?php echo $__env->yieldContent('title', 'SIPAT - Sistema de Planificación y Administración de Transporte'); ?></title>

    <!-- Bootstrap CSS 5.3.0 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6.4.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.min.js"></script>

    <style>
    /* =============================================================================
    SIPAT - DISEÑO PROFESIONAL Y SOBRIO
    Estilo empresarial serio y funcional
    ============================================================================= */

    /* --- PALETA DE COLORES EMPRESARIAL --- */
    :root {
        /* Colores principales - Sobrios y profesionales */
        --sipat-primary: #2563eb;       /* Azul corporativo */
        --sipat-primary-dark: #1d4ed8;
        --sipat-primary-light: #3b82f6;

        --sipat-success: #059669;       /* Verde corporativo */
        --sipat-success-dark: #047857;
        --sipat-success-light: #10b981;

        --sipat-info: #0891b2;          /* Cyan corporativo */
        --sipat-info-dark: #0e7490;
        --sipat-info-light: #06b6d4;

        --sipat-warning: #d97706;       /* Naranja corporativo */
        --sipat-warning-dark: #b45309;
        --sipat-warning-light: #f59e0b;

        --sipat-danger: #dc2626;        /* Rojo corporativo */
        --sipat-danger-dark: #b91c1c;
        --sipat-danger-light: #ef4444;

        /* Grises empresariales */
        --sipat-dark: #111827;          /* Gris muy oscuro */
        --sipat-gray-50: #f9fafb;       /* Gris muy claro */
        --sipat-gray-100: #f3f4f6;      /* Gris claro */
        --sipat-gray-200: #e5e7eb;      /* Gris medio claro */
        --sipat-gray-300: #d1d5db;      /* Gris medio */
        --sipat-gray-400: #9ca3af;      /* Gris */
        --sipat-gray-500: #6b7280;      /* Gris oscuro */
        --sipat-gray-600: #4b5563;      /* Gris muy oscuro */
        --sipat-gray-700: #374151;      /* Gris carbón */
        --sipat-gray-800: #1f2937;      /* Gris casi negro */
        --sipat-gray-900: #111827;      /* Negro gris */

        /* Fondos */
        --sipat-bg-primary: #ffffff;    /* Blanco puro */
        --sipat-bg-secondary: #f8fafc;  /* Gris muy claro */
        --sipat-bg-tertiary: #f1f5f9;   /* Gris claro */

        /* Sombras sutiles */
        --sipat-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --sipat-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --sipat-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --sipat-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    /* --- RESET Y BASE --- */
    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--sipat-bg-secondary);
        color: var(--sipat-gray-800);
        line-height: 1.6;
        margin: 0;
        padding: 0;
    }

    /* --- NAVBAR EMPRESARIAL --- */
    .navbar {
        background-color: var(--sipat-gray-800) !important;
        border-bottom: 3px solid var(--sipat-primary);
        box-shadow: var(--sipat-shadow-md);
        z-index: 9999 !important;
        position: fixed !important;
        top: 0;
        left: 0;
        right: 0;
        height: 64px;
    }

    .navbar-brand {
        font-weight: 700;
        color: #ffffff !important;
        font-size: 1.5rem;
        padding: 12px 16px;
        transition: color 0.3s ease;
    }

    .navbar-brand:hover {
        color: var(--sipat-primary-light) !important;
    }

    .navbar-brand i {
        color: var(--sipat-primary-light);
        margin-right: 8px;
    }

    .navbar-nav .nav-link {
        color: #ffffff !important;
        font-weight: 500;
    }

    /* --- SIDEBAR PROFESIONAL --- */
    .sidebar {
        position: fixed;
        top: 64px;
        bottom: 0;
        left: 0;
        z-index: 1000;
        width: 260px;
        background-color: var(--sipat-bg-primary);
        border-right: 1px solid var(--sipat-gray-200);
        box-shadow: var(--sipat-shadow);
        overflow-y: auto;
    }

    .sidebar-sticky {
        position: sticky;
        top: 0;
        height: calc(100vh - 64px);
        padding: 1rem 0;
        overflow-x: hidden;
        overflow-y: auto;
    }

    /* --- NAVEGACIÓN LIMPIA --- */
    .nav-link {
        font-weight: 500;
        color: var(--sipat-gray-700) !important;
        padding: 12px 20px;
        margin: 2px 12px;
        border-radius: 6px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        font-size: 0.95rem;
        border: 1px solid transparent;
    }

    .nav-link:hover {
        background-color: var(--sipat-gray-50);
        color: var(--sipat-primary) !important;
        border-color: var(--sipat-gray-200);
    }

    .nav-link.active {
        background-color: var(--sipat-primary);
        color: white !important;
        font-weight: 600;
        box-shadow: var(--sipat-shadow-sm);
    }

    .nav-link i {
        margin-right: 10px;
        width: 18px;
        text-align: center;
        font-size: 1rem;
    }

    /* --- CONTENIDO PRINCIPAL --- */
    .main-content {
        margin-left: 260px;
        padding: 84px 24px 24px 24px;
        min-height: 100vh;
        background-color: var(--sipat-bg-secondary);
    }

    /* --- TARJETAS LIMPIAS --- */
    .card {
        background-color: var(--sipat-bg-primary);
        border: 1px solid var(--sipat-gray-200);
        border-radius: 8px;
        box-shadow: var(--sipat-shadow-sm);
        margin-bottom: 24px;
        transition: box-shadow 0.3s ease;
    }

    .card:hover {
        box-shadow: var(--sipat-shadow-md);
    }

    .card-header {
        background-color: var(--sipat-gray-50);
        border-bottom: 1px solid var(--sipat-gray-200);
        padding: 16px 20px;
        font-weight: 600;
        color: var(--sipat-gray-800);
        border-radius: 8px 8px 0 0;
        font-size: 1rem;
    }

    .card-body {
        padding: 20px;
        color: var(--sipat-gray-800) !important;
        background-color: var(--sipat-bg-primary);
    }

    /* --- TARJETAS DE MÉTRICAS LIMPIAS --- */
    .border-left-primary {
        border-left: 4px solid var(--sipat-primary) !important;
    }

    .border-left-success {
        border-left: 4px solid var(--sipat-success) !important;
    }

    .border-left-info {
        border-left: 4px solid var(--sipat-info) !important;
    }

    .border-left-warning {
        border-left: 4px solid var(--sipat-warning) !important;
    }

    .border-left-danger {
        border-left: 4px solid var(--sipat-danger) !important;
    }

    /* --- BOTONES PROFESIONALES --- */
    .btn {
        border-radius: 6px;
        font-weight: 600;
        padding: 10px 16px;
        transition: all 0.3s ease;
        border: 1px solid transparent;
        font-size: 0.875rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }

    .btn-primary {
        background-color: var(--sipat-primary);
        border-color: var(--sipat-primary);
        color: white;
    }

    .btn-primary:hover {
        background-color: var(--sipat-primary-dark);
        border-color: var(--sipat-primary-dark);
        color: white;
        box-shadow: var(--sipat-shadow);
    }

    .btn-success {
        background-color: var(--sipat-success);
        border-color: var(--sipat-success);
        color: white;
    }

    .btn-success:hover {
        background-color: var(--sipat-success-dark);
        border-color: var(--sipat-success-dark);
        color: white;
        box-shadow: var(--sipat-shadow);
    }

    .btn-warning {
        background-color: var(--sipat-warning);
        border-color: var(--sipat-warning);
        color: white;
    }

    .btn-warning:hover {
        background-color: var(--sipat-warning-dark);
        border-color: var(--sipat-warning-dark);
        color: white;
        box-shadow: var(--sipat-shadow);
    }

    .btn-danger {
        background-color: var(--sipat-danger);
        border-color: var(--sipat-danger);
        color: white;
    }

    .btn-danger:hover {
        background-color: var(--sipat-danger-dark);
        border-color: var(--sipat-danger-dark);
        color: white;
        box-shadow: var(--sipat-shadow);
    }

    .btn-info {
        background-color: var(--sipat-info);
        border-color: var(--sipat-info);
        color: white;
    }

    .btn-info:hover {
        background-color: var(--sipat-info-dark);
        border-color: var(--sipat-info-dark);
        color: white;
        box-shadow: var(--sipat-shadow);
    }

    .btn-secondary {
        background-color: var(--sipat-gray-600);
        border-color: var(--sipat-gray-600);
        color: white;
    }

    .btn-secondary:hover {
        background-color: var(--sipat-gray-700);
        border-color: var(--sipat-gray-700);
        color: white;
        box-shadow: var(--sipat-shadow);
    }

    .btn-outline-primary {
        color: var(--sipat-primary);
        border-color: var(--sipat-primary);
        background-color: transparent;
    }

    .btn-outline-primary:hover {
        background-color: var(--sipat-primary);
        border-color: var(--sipat-primary);
        color: white;
    }

    /* Botones pequeños */
    .btn-sm {
        padding: 6px 12px;
        font-size: 0.8rem;
        border-radius: 4px;
    }

    /* --- DROPDOWN MENU --- */
    .dropdown-menu {
        background-color: var(--sipat-bg-primary);
        border: 1px solid var(--sipat-gray-200);
        border-radius: 6px;
        box-shadow: var(--sipat-shadow-lg);
        padding: 8px 0;
        z-index: 9998 !important;
        margin-top: 4px;
    }

    .dropdown-item {
        padding: 8px 16px;
        color: var(--sipat-gray-700);
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .dropdown-item:hover {
        background-color: var(--sipat-gray-50);
        color: var(--sipat-primary);
    }

    .dropdown-toggle::after {
        border-color: currentColor transparent transparent transparent;
    }

    /* --- TABLAS PROFESIONALES --- */
    .table {
        background-color: var(--sipat-bg-primary);
        color: var(--sipat-gray-800);
        border-radius: 8px;
        overflow: hidden;
        box-shadow: var(--sipat-shadow-sm);
        border: 1px solid var(--sipat-gray-200);
    }

    .table thead th {
        background-color: var(--sipat-gray-100);
        border-bottom: 2px solid var(--sipat-gray-200);
        color: var(--sipat-gray-800);
        font-weight: 700;
        font-size: 0.875rem;
        padding: 14px 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table tbody td {
        padding: 12px;
        vertical-align: middle;
        border-bottom: 1px solid var(--sipat-gray-100);
        color: var(--sipat-gray-700);
    }

    .table-striped > tbody > tr:nth-of-type(odd) > td {
        background-color: var(--sipat-gray-50);
    }

    .table-hover tbody tr:hover {
        background-color: var(--sipat-primary);
        color: white;
    }

    .table-hover tbody tr:hover td {
        color: white;
    }

    .table-warning {
        background-color: rgba(217, 119, 6, 0.1) !important;
    }

    /* --- BADGES PROFESIONALES --- */
    .badge {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bg-success {
        background-color: var(--sipat-success) !important;
        color: white;
    }

    .bg-danger {
        background-color: var(--sipat-danger) !important;
        color: white;
    }

    .bg-warning {
        background-color: var(--sipat-warning) !important;
        color: white;
    }

    .bg-info {
        background-color: var(--sipat-info) !important;
        color: white;
    }

    .bg-primary {
        background-color: var(--sipat-primary) !important;
        color: white;
    }

    .bg-secondary {
        background-color: var(--sipat-gray-600) !important;
        color: white;
    }

    /* --- ALERTAS EMPRESARIALES --- */
    .alert {
        border-radius: 6px;
        border: 1px solid;
        padding: 16px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .alert-success {
        background-color: #f0fdf4;
        border-color: var(--sipat-success-light);
        color: var(--sipat-success-dark);
    }

    .alert-danger {
        background-color: #fef2f2;
        border-color: var(--sipat-danger-light);
        color: var(--sipat-danger-dark);
    }

    .alert-warning {
        background-color: #fffbeb;
        border-color: var(--sipat-warning-light);
        color: var(--sipat-warning-dark);
    }

    .alert-info {
        background-color: #f0f9ff;
        border-color: var(--sipat-info-light);
        color: var(--sipat-info-dark);
    }

    /* --- FORMULARIOS LIMPIOS --- */
    .form-control, .form-select {
        border-radius: 6px;
        border: 1px solid var(--sipat-gray-300);
        padding: 10px 12px;
        transition: all 0.3s ease;
        background-color: var(--sipat-bg-primary);
        color: var(--sipat-gray-800);
        font-size: 0.875rem;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--sipat-primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        background-color: var(--sipat-bg-primary);
        outline: none;
    }

    .form-label {
        font-weight: 600;
        color: var(--sipat-gray-700);
        margin-bottom: 6px;
        font-size: 0.875rem;
    }

    /* --- BARRAS DE PROGRESO SIMPLES --- */
    .progress {
        height: 8px;
        border-radius: 4px;
        background-color: var(--sipat-gray-200);
        overflow: hidden;
    }

    .progress-bar {
        border-radius: 4px;
        transition: width 1s ease-in-out;
    }

    .progress-bar.bg-primary {
        background-color: var(--sipat-primary);
    }

    .progress-bar.bg-success {
        background-color: var(--sipat-success);
    }

    .progress-bar.bg-info {
        background-color: var(--sipat-info);
    }

    .progress-bar.bg-warning {
        background-color: var(--sipat-warning);
    }

    .progress-bar.bg-danger {
        background-color: var(--sipat-danger);
    }

    /* --- ESTADO DEL SIDEBAR --- */
    .sidebar-heading {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
        color: var(--sipat-gray-500);
        margin: 16px 20px 8px 20px;
    }

    .sidebar .small {
        font-size: 0.8rem;
        color: var(--sipat-gray-600);
        padding: 0 20px;
    }

    /* --- TEXTOS Y UTILIDADES --- */
    .text-gray-800 { color: var(--sipat-gray-800) !important; }
    .text-gray-700 { color: var(--sipat-gray-700) !important; }
    .text-gray-600 { color: var(--sipat-gray-600) !important; }
    .text-gray-500 { color: var(--sipat-gray-500) !important; }
    .text-gray-300 { color: var(--sipat-gray-300) !important; }
    .text-muted { color: var(--sipat-gray-500) !important; }

    .text-primary { color: var(--sipat-primary) !important; }
    .text-success { color: var(--sipat-success) !important; }
    .text-info { color: var(--sipat-info) !important; }
    .text-warning { color: var(--sipat-warning) !important; }
    .text-danger { color: var(--sipat-danger) !important; }

    .font-weight-bold { font-weight: 700 !important; }
    .font-weight-normal { font-weight: 400 !important; }
    .text-xs { font-size: 0.75rem !important; }
    .text-sm { font-size: 0.875rem !important; }
    .text-uppercase { text-transform: uppercase !important; }

    /* --- UTILIDADES DE ESPACIADO --- */
    .shadow { box-shadow: var(--sipat-shadow) !important; }
    .shadow-sm { box-shadow: var(--sipat-shadow-sm) !important; }
    .shadow-lg { box-shadow: var(--sipat-shadow-lg) !important; }

    /* --- RESPONSIVE DESIGN --- */
    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            top: 64px;
            left: -260px;
            width: 260px;
            transition: left 0.3s ease;
            z-index: 1001;
        }

        .sidebar.show {
            left: 0;
            box-shadow: var(--sipat-shadow-lg);
        }

        .main-content {
            margin-left: 0;
            padding: 84px 16px 16px 16px;
        }

        .navbar-brand {
            font-size: 1.25rem;
        }
    }

    @media (max-width: 576px) {
        .btn {
            font-size: 0.8rem;
            padding: 8px 12px;
        }

        .card-body {
            padding: 16px;
        }

        .table-responsive {
            font-size: 0.8rem;
        }

        .main-content {
            padding: 84px 12px 12px 12px;
        }
    }

    /* --- FIXES CRÍTICOS --- */
    .card-body, .card-title, .card-text,
    .h1, .h2, .h3, .h4, .h5, .h6,
    h1, h2, h3, h4, h5, h6, p, span, div, label {
        color: var(--sipat-gray-800) !important;
    }

    /* Asegurar fondos correctos */
    .container-fluid, .row {
        background-color: transparent !important;
    }

    /* --- BOTONES DE GRUPO --- */
    .btn-group .btn {
        border-radius: 0;
    }

    .btn-group .btn:first-child {
        border-radius: 6px 0 0 6px;
    }

    .btn-group .btn:last-child {
        border-radius: 0 6px 6px 0;
    }

    .btn-group .btn:only-child {
        border-radius: 6px;
    }

    /* --- PAGINACIÓN --- */
    .pagination {
        margin: 0;
    }

    .page-link {
        color: var(--sipat-primary);
        background-color: var(--sipat-bg-primary);
        border: 1px solid var(--sipat-gray-300);
        padding: 8px 12px;
    }

    .page-link:hover {
        color: var(--sipat-primary-dark);
        background-color: var(--sipat-gray-50);
        border-color: var(--sipat-gray-300);
    }

    .page-item.active .page-link {
        background-color: var(--sipat-primary);
        border-color: var(--sipat-primary);
        color: white;
    }

    /* --- SCROLLBAR SUTIL --- */
    ::-webkit-scrollbar {
        width: 6px;
    }

    ::-webkit-scrollbar-track {
        background: var(--sipat-gray-100);
    }

    ::-webkit-scrollbar-thumb {
        background: var(--sipat-gray-400);
        border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--sipat-gray-500);
    }

    /* --- Z-INDEX HIERARCHY --- */
    .navbar { z-index: 9999 !important; }
    .dropdown-menu { z-index: 9998 !important; }
    .modal { z-index: 9997 !important; }
    .sidebar { z-index: 1000 !important; }
    .main-content { z-index: 100 !important; }
    </style>

    <?php echo $__env->yieldContent('styles'); ?>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="<?php echo e(route('dashboard')); ?>">
            <i class="fas fa-bus me-2"></i> SIPAT
        </a>

        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <span class="nav-link px-3 text-white">
                    <i class="fas fa-circle text-success me-1"></i> Sistema Operativo
                </span>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="sidebar-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->is('dashboard') || request()->is('/') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard')); ?>">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->is('conductores*') ? 'active' : ''); ?>" href="<?php echo e(route('conductores.index')); ?>">
                                <i class="fas fa-users"></i> Conductores
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->is('validaciones*') ? 'active' : ''); ?>" href="<?php echo e(route('validaciones.index')); ?>">
                                <i class="fas fa-check-circle"></i> Validaciones
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->is('plantillas*') ? 'active' : ''); ?>" href="<?php echo e(route('plantillas.index')); ?>">
                                <i class="fas fa-calendar-alt"></i> Plantillas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->is('parametros*') ? 'active' : ''); ?>" href="<?php echo e(route('parametros.index')); ?>">
                                <i class="fas fa-cogs"></i> Parámetros
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->is('reportes*') ? 'active' : ''); ?>" href="<?php echo e(route('reportes.index')); ?>">
                                <i class="fas fa-chart-line"></i> Reportes
                            </a>
                        </li>
                    </ul>

                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>Estado del Sistema</span>
                    </h6>

                    <div class="px-3">
                        <small class="text-muted">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Conductores Activos:</span>
                                <span class="text-success fw-bold">
                                    <?php echo e(\App\Models\Conductor::where('estado', 'DISPONIBLE')->count()); ?>

                                </span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Validaciones:</span>
                                <span class="text-warning fw-bold">
                                    <?php echo e(\App\Models\Validacion::where('estado', 'PENDIENTE')->count()); ?>

                                </span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Uptime:</span>
                                <span class="text-success fw-bold">99.9%</span>
                            </div>
                        </small>
                    </div>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Alert Messages -->
                <?php if(session('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo e(session('success')); ?>

                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(session('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo e(session('error')); ?>

                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(session('warning')): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo e(session('warning')); ?>

                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(session('info')): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php echo e(session('info')); ?>

                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Main Content Area -->
                <?php echo $__env->yieldContent('content'); ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Common JavaScript -->
    <script>
        // Set CSRF token for AJAX requests
        window.Laravel = {
            csrfToken: '<?php echo e(csrf_token()); ?>'
        };

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>

    <?php echo $__env->yieldContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\sipat-laravel\resources\views/layouts/app.blade.php ENDPATH**/ ?>
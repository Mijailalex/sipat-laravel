<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'SIPAT - Sistema de Planificación')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #f8f9fa;
        }

        .sidebar-sticky {
            position: sticky;
            top: 48px;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .navbar-brand {
            font-weight: bold;
            color: #007bff !important;
        }

        .nav-link {
            font-weight: 500;
            color: #333;
            padding: 10px 15px;
            margin: 2px 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .nav-link:hover {
            background-color: #e7f3ff;
            color: #007bff;
        }

        .nav-link.active {
            color: #007bff;
            background-color: #e7f3ff;
            border-radius: 5px;
        }

        .main-content {
            margin-left: 240px;
            padding: 20px;
        }

        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .metric-card.green {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }

        .metric-card.red {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
        }

        .metric-card.yellow {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-disponible { background-color: #d4edda; color: #155724; }
        .status-descanso { background-color: #cce7ff; color: #004085; }
        .status-vacaciones { background-color: #e7d4ff; color: #4a148c; }
        .status-suspendido { background-color: #f8d7da; color: #721c24; }

        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: static;
                height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>

    @yield('styles')
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="/dashboard">
            <i class="fas fa-bus"></i> SIPAT
        </a>

        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <span class="nav-link px-3 text-white">
                    <i class="fas fa-circle text-success"></i> Sistema Operativo
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
                            <a class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}" href="/dashboard">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('conductores*') ? 'active' : '' }}" href="/conductores">
                                <i class="fas fa-users"></i> Conductores
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('validaciones*') ? 'active' : '' }}" href="/validaciones">
                                <i class="fas fa-check-circle"></i> Validaciones
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('plantillas*') ? 'active' : '' }}" href="/plantillas">
                                <i class="fas fa-calendar-alt"></i> Plantillas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('parametros*') ? 'active' : '' }}" href="/parametros">
                                <i class="fas fa-cogs"></i> Parámetros
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('reportes*') ? 'active' : '' }}" href="/reportes">
                                <i class="fas fa-chart-line"></i> Reportes
                            </a>
                        </li>
                    </ul>

                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>Estado del Sistema</span>
                    </h6>

                    <div class="px-3">
                        <small class="text-muted">
                            <div class="d-flex justify-content-between">
                                <span>Conductores Activos:</span>
                                <span class="text-success">{{ \App\Models\Conductor::where('estado', 'DISPONIBLE')->count() }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Validaciones:</span>
                                <span class="text-warning">{{ \App\Models\Validacion::where('estado', 'PENDIENTE')->count() }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Uptime:</span>
                                <span class="text-success">99.9%</span>
                            </div>
                        </small>
                    </div>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @yield('scripts')
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAT - Dashboard Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --sipat-primary: #1e3a8a;
            --sipat-secondary: #3b82f6;
            --sipat-success: #10b981;
            --sipat-warning: #f59e0b;
            --sipat-danger: #ef4444;
            --sipat-dark: #1f2937;
            --sipat-light: #f8fafc;
        }

        body {
            background: linear-gradient(135deg, var(--sipat-light) 0%, #e2e8f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, var(--sipat-primary) 0%, var(--sipat-secondary) 100%);
            box-shadow: 0 2px 20px rgba(30, 58, 138, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .metric-card {
            text-align: center;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--sipat-primary), var(--sipat-secondary));
        }

        .metric-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .metric-label {
            font-size: 0.875rem;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .alert-card {
            border-left: 4px solid;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .alert-card:hover {
            transform: translateX(4px);
        }

        .alert-critical {
            border-left-color: var(--sipat-danger);
            background: linear-gradient(90deg, rgba(239, 68, 68, 0.05), rgba(239, 68, 68, 0.02));
        }

        .alert-warning {
            border-left-color: var(--sipat-warning);
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.05), rgba(245, 158, 11, 0.02));
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        .status-operational {
            background-color: var(--sipat-success);
        }

        .status-warning {
            background-color: var(--sipat-warning);
        }

        .status-critical {
            background-color: var(--sipat-danger);
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }

        .chart-small {
            height: 200px;
        }

        .progress-custom {
            height: 8px;
            border-radius: 10px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .progress-bar-custom {
            height: 100%;
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        .table-modern {
            border-radius: 12px;
            overflow: hidden;
            background: white;
        }

        .table-modern thead {
            background: linear-gradient(135deg, var(--sipat-primary) 0%, var(--sipat-secondary) 100%);
        }

        .table-modern thead th {
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .table-modern tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }

        .badge-status {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .btn-refresh {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--sipat-primary), var(--sipat-secondary));
            border: none;
            color: white;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.3);
            transition: all 0.3s ease;
        }

        .btn-refresh:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(30, 58, 138, 0.4);
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--sipat-danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        }

        .spinner {
            width: 3rem;
            height: 3rem;
            border: 3px solid var(--sipat-light);
            border-top: 3px solid var(--sipat-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .auto-refresh-indicator {
            position: fixed;
            top: 80px;
            right: 20px;
            background: rgba(16, 185, 129, 0.9);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            z-index: 1000;
            display: none;
        }

        @media (max-width: 768px) {
            .metric-value {
                font-size: 2rem;
            }

            .metric-icon {
                font-size: 2rem;
            }

            .chart-container {
                height: 250px;
            }

            .btn-refresh {
                bottom: 1rem;
                right: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Overlay de carga -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Indicador de auto-refresh -->
    <div class="auto-refresh-indicator" id="autoRefreshIndicator">
        <i class="fas fa-sync-alt fa-spin"></i> Actualizando datos...
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-bus-alt me-2"></i>
                SIPAT Dashboard
            </a>

            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell position-relative">
                            <span class="notification-badge" id="notificationBadge">0</span>
                        </i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" id="notificationDropdown">
                        <li><h6 class="dropdown-header">Notificaciones</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#">Cargando...</a></li>
                    </ul>
                </div>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> Admin
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Configuración</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-chart-bar me-2"></i>Reportes</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Container principal -->
    <div class="container-fluid mt-4">
        <!-- Estado del sistema -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-heartbeat me-2"></i>Estado del Sistema
                        </h5>
                        <div class="d-flex align-items-center">
                            <span class="status-indicator status-operational" id="systemStatusIndicator"></span>
                            <span id="systemStatusText">Sistema Operativo</span>
                            <span class="ms-auto text-muted" id="lastUpdate">Última actualización: Cargando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Métricas principales -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <i class="fas fa-users metric-icon text-primary"></i>
                    <div class="metric-value text-primary" id="totalConductores">0</div>
                    <div class="metric-label">Total Conductores</div>
                    <div class="mt-2">
                        <small class="text-success" id="conductoresDisponibles">0 disponibles</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <i class="fas fa-exclamation-triangle metric-icon text-warning"></i>
                    <div class="metric-value text-warning" id="validacionesPendientes">0</div>
                    <div class="metric-label">Validaciones Pendientes</div>
                    <div class="mt-2">
                        <small class="text-danger" id="validacionesCriticas">0 críticas</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <i class="fas fa-bus metric-icon text-info"></i>
                    <div class="metric-value text-info" id="turnosHoy">0</div>
                    <div class="metric-label">Turnos Hoy</div>
                    <div class="mt-2">
                        <small class="text-success" id="turnosCompletados">0 completados</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card">
                    <i class="fas fa-chart-line metric-icon text-success"></i>
                    <div class="metric-value text-success" id="eficienciaGeneral">0%</div>
                    <div class="metric-label">Eficiencia General</div>
                    <div class="mt-2">
                        <small class="text-info" id="tendenciaEficiencia">Estable</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas críticas -->
        <div class="row mb-4" id="alertasSection" style="display: none;">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>Alertas Críticas
                        </h5>
                        <div id="alertasContainer">
                            <!-- Las alertas se cargarán dinámicamente -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos y datos -->
        <div class="row mb-4">
            <!-- Gráfico de tendencias -->
            <div class="col-lg-8 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-chart-area me-2"></i>Tendencias Semanales
                        </h5>
                        <div class="chart-container">
                            <canvas id="tendenciasChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distribución de estados -->
            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-chart-pie me-2"></i>Estados de Conductores
                        </h5>
                        <div class="chart-container chart-small">
                            <canvas id="estadosChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progreso de recursos del sistema -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-memory me-2"></i>Uso de Memoria
                        </h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span id="memoriaTexto">0 MB / 0 MB</span>
                            <span id="memoriaPorcentaje">0%</span>
                        </div>
                        <div class="progress-custom">
                            <div class="progress-bar-custom bg-info" id="memoriaBar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-database me-2"></i>Base de Datos
                        </h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span id="bdTexto">Tiempo de respuesta</span>
                            <span id="bdTiempo">0 ms</span>
                        </div>
                        <div class="progress-custom">
                            <div class="progress-bar-custom bg-success" id="bdBar" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conductores críticos -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-users-slash me-2"></i>Conductores Críticos
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th>Días Acumulados</th>
                                        <th>Eficiencia</th>
                                        <th>Puntualidad</th>
                                        <th>Razones</th>
                                        <th>Prioridad</th>
                                    </tr>
                                </thead>
                                <tbody id="conductoresCriticosTable">
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Cargando...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Validaciones recientes -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-clipboard-list me-2"></i>Validaciones Recientes
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Tipo</th>
                                        <th>Conductor</th>
                                        <th>Severidad</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="validacionesRecientesTable">
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Cargando...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón de refresh -->
    <button class="btn btn-refresh" id="refreshBtn" title="Actualizar datos">
        <i class="fas fa-sync-alt"></i>
    </button>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.min.js"></script>
    <script>
        // Variables globales
        let tendenciasChart = null;
        let estadosChart = null;
        let autoRefreshInterval = null;
        const AUTO_REFRESH_INTERVAL = 30000; // 30 segundos

        // Configuración de colores
        const colors = {
            primary: '#1e3a8a',
            secondary: '#3b82f6',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#06b6d4'
        };

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
            setupEventListeners();
            startAutoRefresh();
        });

        function initializeDashboard() {
            hideLoadingOverlay();
            loadDashboardData();
            initializeCharts();
        }

        function setupEventListeners() {
            document.getElementById('refreshBtn').addEventListener('click', function() {
                this.querySelector('i').classList.add('fa-spin');
                loadDashboardData();
                setTimeout(() => {
                    this.querySelector('i').classList.remove('fa-spin');
                }, 1000);
            });
        }

        function startAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                showAutoRefreshIndicator();
                loadDashboardData();
                setTimeout(hideAutoRefreshIndicator, 2000);
            }, AUTO_REFRESH_INTERVAL);
        }

        function showAutoRefreshIndicator() {
            document.getElementById('autoRefreshIndicator').style.display = 'block';
        }

        function hideAutoRefreshIndicator() {
            document.getElementById('autoRefreshIndicator').style.display = 'none';
        }

        function hideLoadingOverlay() {
            setTimeout(() => {
                document.getElementById('loadingOverlay').style.display = 'none';
            }, 500);
        }

        function loadDashboardData() {
            // Simular datos del dashboard (en implementación real vendría de API)
            const data = generateMockData();
            updateDashboard(data);
        }

        function generateMockData() {
            return {
                sistema: {
                    estado: 'OPERATIVO',
                    mensaje: 'Sistema funcionando normalmente',
                    timestamp: new Date().toISOString()
                },
                metricas: {
                    conductores: {
                        total: 156,
                        disponibles: 89,
                        en_descanso: 23,
                        criticos: 5,
                        eficiencia_promedio: 87.5
                    },
                    validaciones: {
                        pendientes: 12,
                        criticas: 3,
                        resueltas_hoy: 8
                    },
                    turnos: {
                        hoy: 45,
                        completados: 32,
                        en_curso: 8,
                        cobertura: 95.5
                    },
                    eficiencia: {
                        general: 87.5,
                        tendencia: 'MEJORANDO'
                    },
                    sistema: {
                        memoria: {
                            uso_mb: 512,
                            limite_mb: 1024,
                            porcentaje: 50
                        },
                        bd: {
                            tiempo_respuesta: 45,
                            estado: 'OK'
                        }
                    }
                },
                alertas: [
                    {
                        tipo: 'CRITICA',
                        mensaje: '3 conductores necesitan descanso urgente',
                        componente: 'conductores'
                    },
                    {
                        tipo: 'ADVERTENCIA',
                        mensaje: '5 validaciones están próximas a vencer',
                        componente: 'validaciones'
                    }
                ],
                tendencias: {
                    labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                    turnos: [42, 38, 45, 41, 47, 35, 40],
                    eficiencia: [85, 87, 88, 86, 89, 87, 88],
                    validaciones: [8, 12, 6, 9, 11, 7, 5]
                },
                estados_conductores: {
                    labels: ['Disponible', 'Descanso', 'Ocupado', 'Inactivo'],
                    data: [89, 23, 31, 13]
                },
                conductores_criticos: [
                    {
                        codigo: 'CON001',
                        nombre: 'Juan Pérez',
                        estado: 'DISPONIBLE',
                        dias_acumulados: 7,
                        eficiencia: 75,
                        puntualidad: 82,
                        razones: ['Necesita descanso', 'Eficiencia baja'],
                        prioridad: 85
                    },
                    {
                        codigo: 'CON045',
                        nombre: 'María García',
                        estado: 'DISPONIBLE',
                        dias_acumulados: 6,
                        eficiencia: 78,
                        puntualidad: 79,
                        razones: ['Necesita descanso'],
                        prioridad: 75
                    }
                ],
                validaciones_recientes: [
                    {
                        codigo: 'VAL-DES-001',
                        tipo: 'DESCANSO_001',
                        conductor: 'Juan Pérez (CON001)',
                        severidad: 'CRITICA',
                        estado: 'PENDIENTE',
                        fecha: '2025-07-11 14:30:00'
                    },
                    {
                        codigo: 'VAL-EFI-002',
                        tipo: 'EFICIENCIA_002',
                        conductor: 'Ana López (CON023)',
                        severidad: 'ADVERTENCIA',
                        estado: 'EN_REVISION',
                        fecha: '2025-07-11 13:45:00'
                    }
                ]
            };
        }

        function updateDashboard(data) {
            updateSystemStatus(data.sistema);
            updateMetrics(data.metricas);
            updateAlerts(data.alertas);
            updateCharts(data.tendencias, data.estados_conductores);
            updateConductoresCriticos(data.conductores_criticos);
            updateValidacionesRecientes(data.validaciones_recientes);
            updateNotifications();
            updateLastUpdate();
        }

        function updateSystemStatus(sistema) {
            const indicator = document.getElementById('systemStatusIndicator');
            const text = document.getElementById('systemStatusText');

            indicator.className = 'status-indicator';

            switch(sistema.estado) {
                case 'OPERATIVO':
                    indicator.classList.add('status-operational');
                    break;
                case 'ALERTA':
                    indicator.classList.add('status-warning');
                    break;
                case 'CRITICO':
                    indicator.classList.add('status-critical');
                    break;
            }

            text.textContent = sistema.mensaje;
        }

        function updateMetrics(metricas) {
            document.getElementById('totalConductores').textContent = metricas.conductores.total;
            document.getElementById('conductoresDisponibles').textContent = `${metricas.conductores.disponibles} disponibles`;
            document.getElementById('validacionesPendientes').textContent = metricas.validaciones.pendientes;
            document.getElementById('validacionesCriticas').textContent = `${metricas.validaciones.criticas} críticas`;
            document.getElementById('turnosHoy').textContent = metricas.turnos.hoy;
            document.getElementById('turnosCompletados').textContent = `${metricas.turnos.completados} completados`;
            document.getElementById('eficienciaGeneral').textContent = `${metricas.eficiencia.general}%`;
            document.getElementById('tendenciaEficiencia').textContent =
                metricas.eficiencia.tendencia === 'MEJORANDO' ? 'Mejorando' :
                metricas.eficiencia.tendencia === 'DECLINANDO' ? 'Declinando' : 'Estable';

            // Recursos del sistema
            const memoria = metricas.sistema.memoria;
            document.getElementById('memoriaTexto').textContent = `${memoria.uso_mb} MB / ${memoria.limite_mb} MB`;
            document.getElementById('memoriaPorcentaje').textContent = `${memoria.porcentaje}%`;
            document.getElementById('memoriaBar').style.width = `${memoria.porcentaje}%`;

            if (memoria.porcentaje > 90) {
                document.getElementById('memoriaBar').className = 'progress-bar-custom bg-danger';
            } else if (memoria.porcentaje > 75) {
                document.getElementById('memoriaBar').className = 'progress-bar-custom bg-warning';
            } else {
                document.getElementById('memoriaBar').className = 'progress-bar-custom bg-success';
            }

            const bd = metricas.sistema.bd;
            document.getElementById('bdTiempo').textContent = `${bd.tiempo_respuesta} ms`;

            if (bd.tiempo_respuesta > 1000) {
                document.getElementById('bdBar').className = 'progress-bar-custom bg-danger';
                document.getElementById('bdBar').style.width = '30%';
            } else if (bd.tiempo_respuesta > 500) {
                document.getElementById('bdBar').className = 'progress-bar-custom bg-warning';
                document.getElementById('bdBar').style.width = '70%';
            } else {
                document.getElementById('bdBar').className = 'progress-bar-custom bg-success';
                document.getElementById('bdBar').style.width = '100%';
            }
        }

        function updateAlerts(alertas) {
            const section = document.getElementById('alertasSection');
            const container = document.getElementById('alertasContainer');

            if (alertas.length > 0) {
                section.style.display = 'block';
                container.innerHTML = '';

                alertas.forEach(alerta => {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = `alert-card ${alerta.tipo === 'CRITICA' ? 'alert-critical' : 'alert-warning'}`;
                    alertDiv.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="fas ${alerta.tipo === 'CRITICA' ? 'fa-exclamation-circle' : 'fa-exclamation-triangle'}
                               text-${alerta.tipo === 'CRITICA' ? 'danger' : 'warning'} me-3"></i>
                            <div>
                                <strong>${alerta.tipo}</strong>: ${alerta.mensaje}
                                <small class="d-block text-muted">Componente: ${alerta.componente}</small>
                            </div>
                        </div>
                    `;
                    container.appendChild(alertDiv);
                });
            } else {
                section.style.display = 'none';
            }
        }

        function initializeCharts() {
            // Gráfico de tendencias
            const tendenciasCtx = document.getElementById('tendenciasChart').getContext('2d');
            tendenciasChart = new Chart(tendenciasCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });

            // Gráfico de estados
            const estadosCtx = document.getElementById('estadosChart').getContext('2d');
            estadosChart = new Chart(estadosCtx, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: []
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
        }

        function updateCharts(tendencias, estadosConductores) {
            // Actualizar gráfico de tendencias
            tendenciasChart.data.labels = tendencias.labels;
            tendenciasChart.data.datasets = [
                {
                    label: 'Turnos',
                    data: tendencias.turnos,
                    borderColor: colors.primary,
                    backgroundColor: colors.primary + '20',
                    tension: 0.3
                },
                {
                    label: 'Eficiencia (%)',
                    data: tendencias.eficiencia,
                    borderColor: colors.success,
                    backgroundColor: colors.success + '20',
                    tension: 0.3
                },
                {
                    label: 'Validaciones',
                    data: tendencias.validaciones,
                    borderColor: colors.warning,
                    backgroundColor: colors.warning + '20',
                    tension: 0.3
                }
            ];
            tendenciasChart.update();

            // Actualizar gráfico de estados
            estadosChart.data.labels = estadosConductores.labels;
            estadosChart.data.datasets = [{
                data: estadosConductores.data,
                backgroundColor: [
                    colors.success,
                    colors.info,
                    colors.warning,
                    colors.danger
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }];
            estadosChart.update();
        }

        function updateConductoresCriticos(conductores) {
            const tbody = document.getElementById('conductoresCriticosTable');
            tbody.innerHTML = '';

            if (conductores.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4 text-success">
                            <i class="fas fa-check-circle me-2"></i>
                            No hay conductores críticos
                        </td>
                    </tr>
                `;
                return;
            }

            conductores.forEach(conductor => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${conductor.codigo}</strong></td>
                    <td>${conductor.nombre}</td>
                    <td><span class="badge badge-status bg-${getEstadoColor(conductor.estado)}">${conductor.estado}</span></td>
                    <td><span class="badge bg-danger">${conductor.dias_acumulados} días</span></td>
                    <td><span class="badge bg-${conductor.eficiencia < 80 ? 'danger' : 'warning'}">${conductor.eficiencia}%</span></td>
                    <td><span class="badge bg-${conductor.puntualidad < 85 ? 'danger' : 'warning'}">${conductor.puntualidad}%</span></td>
                    <td>
                        ${conductor.razones.map(razon => `<small class="d-block text-muted">${razon}</small>`).join('')}
                    </td>
                    <td>
                        <div class="progress-custom" style="width: 60px;">
                            <div class="progress-bar-custom bg-${conductor.prioridad > 80 ? 'danger' : 'warning'}"
                                 style="width: ${conductor.prioridad}%"></div>
                        </div>
                        <small class="text-muted">${conductor.prioridad}</small>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function updateValidacionesRecientes(validaciones) {
            const tbody = document.getElementById('validacionesRecientesTable');
            tbody.innerHTML = '';

            if (validaciones.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4 text-success">
                            <i class="fas fa-check-circle me-2"></i>
                            No hay validaciones recientes
                        </td>
                    </tr>
                `;
                return;
            }

            validaciones.forEach(validacion => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${validacion.codigo}</strong></td>
                    <td><small>${validacion.tipo}</small></td>
                    <td>${validacion.conductor}</td>
                    <td><span class="badge badge-status bg-${getSeveridadColor(validacion.severidad)}">${validacion.severidad}</span></td>
                    <td><span class="badge badge-status bg-${getEstadoValidacionColor(validacion.estado)}">${validacion.estado}</span></td>
                    <td><small>${formatearFecha(validacion.fecha)}</small></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="verValidacion('${validacion.codigo}')">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${validacion.estado === 'PENDIENTE' ?
                            `<button class="btn btn-sm btn-outline-success ms-1" onclick="resolverValidacion('${validacion.codigo}')">
                                <i class="fas fa-check"></i>
                            </button>` : ''
                        }
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function updateNotifications() {
            // Simular notificaciones
            const count = Math.floor(Math.random() * 5) + 1;
            document.getElementById('notificationBadge').textContent = count;
        }

        function updateLastUpdate() {
            document.getElementById('lastUpdate').textContent =
                `Última actualización: ${new Date().toLocaleTimeString('es-ES')}`;
        }

        // Funciones auxiliares
        function getEstadoColor(estado) {
            const colores = {
                'DISPONIBLE': 'success',
                'OCUPADO': 'warning',
                'DESCANSO FISICO': 'info',
                'DESCANSO SEMANAL': 'info',
                'INACTIVO': 'danger',
                'SUSPENDIDO': 'danger'
            };
            return colores[estado] || 'secondary';
        }

        function getSeveridadColor(severidad) {
            const colores = {
                'CRITICA': 'danger',
                'ADVERTENCIA': 'warning',
                'INFO': 'info'
            };
            return colores[severidad] || 'secondary';
        }

        function getEstadoValidacionColor(estado) {
            const colores = {
                'PENDIENTE': 'warning',
                'EN_REVISION': 'info',
                'RESUELTO': 'success',
                'RECHAZADO': 'danger'
            };
            return colores[estado] || 'secondary';
        }

        function formatearFecha(fecha) {
            return new Date(fecha).toLocaleString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Funciones de acción
        function verValidacion(codigo) {
            alert(`Ver validación: ${codigo}`);
        }

        function resolverValidacion(codigo) {
            if (confirm(`¿Resolver validación ${codigo}?`)) {
                alert(`Validación ${codigo} resuelta`);
                loadDashboardData(); // Recargar datos
            }
        }

        // Cleanup al cerrar la página
        window.addEventListener('beforeunload', function() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        });
    </script>
</body>
</html>

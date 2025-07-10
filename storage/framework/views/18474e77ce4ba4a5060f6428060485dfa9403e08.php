<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPAT - Sistema de Planificación de Transporte</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-collapsed {
            width: 70px;
        }
        .sidebar-collapsed .menu-text,
        .sidebar-collapsed .logo-text,
        .sidebar-collapsed .user-info {
            opacity: 0;
            visibility: hidden;
        }
        .sidebar-collapsed .menu-item {
            justify-content: center;
        }
        .content-area {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .content-expanded {
            margin-left: 70px;
        }
        .dashboard-card {
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .dashboard-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in;
        }
        .tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .notification-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .status-disponible { background-color: #d1fae5; color: #065f46; }
        .status-descanso { background-color: #fef3c7; color: #92400e; }
        .status-vacaciones { background-color: #dbeafe; color: #1e40af; }
        .status-suspendido { background-color: #fee2e2; color: #991b1b; }
        .status-operativo { background-color: #d1fae5; color: #065f46; }
        .status-mantenimiento { background-color: #fef3c7; color: #92400e; }
        .status-fuera-servicio { background-color: #fee2e2; color: #991b1b; }

        .metric-card {
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
            background: linear-gradient(90deg, #10b981, #3b82f6, #8b5cf6, #f59e0b);
        }

        .validation-item {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .validation-critica { border-left-color: #ef4444; }
        .validation-advertencia { border-left-color: #f59e0b; }
        .validation-info { border-left-color: #3b82f6; }

        .planning-grid {
            display: grid;
            grid-template-columns: 200px repeat(7, 1fr);
            gap: 1px;
            background: #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .planning-cell {
            background: white;
            padding: 8px;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            text-align: center;
            position: relative;
        }
        .planning-header {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
            min-height: 50px;
        }
        .planning-driver {
            background: #f9fafb;
            font-weight: 500;
            justify-content: flex-start;
            align-items: flex-start;
            padding: 12px 8px;
            text-align: left;
        }

        /* Estilos para múltiples turnos */
        .turno-item {
            width: 100%;
            margin: 2px 0;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 10px;
            line-height: 1.2;
        }
        .turno-larga { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .turno-corta { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .turno-descanso { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }
        .turno-multiple { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }

        .progress-bar {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #3b82f6);
            transition: width 0.3s ease;
        }

        .algorithm-status {
            padding: 1rem;
            border-radius: 8px;
            margin: 0.5rem 0;
        }
        .status-success { background: #d1fae5; border-left: 4px solid #10b981; }
        .status-processing { background: #dbeafe; border-left: 4px solid #3b82f6; }
        .status-pending { background: #f3f4f6; border-left: 4px solid #6b7280; }
        .status-error { background: #fee2e2; border-left: 4px solid #ef4444; }

        .loading-spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Main Container -->
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar bg-gradient-to-br from-blue-900 via-blue-800 to-purple-900 text-white w-64 fixed h-full flex flex-col shadow-2xl">
            <!-- Logo -->
            <div class="p-4 flex items-center border-b border-blue-700/50">
                <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-lg">
                    <i class="fas fa-bus text-blue-800 text-xl"></i>
                </div>
                <div class="ml-3 logo-text">
                    <span class="font-bold text-xl">SIPAT</span>
                    <div class="text-xs text-blue-200">Sistema de Planificación</div>
                </div>
            </div>

            <!-- User Profile -->
            <div class="p-4 flex items-center border-b border-blue-700/50">
                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center">
                    <i class="fas fa-user text-white text-xl"></i>
                </div>
                <div class="ml-3 user-info">
                    <div class="font-medium" id="username">Admin User</div>
                    <div class="text-xs text-blue-200" id="userrole">Administrador</div>
                    <div class="text-xs text-green-300">● En línea</div>
                </div>
            </div>

            <!-- Menu Container -->
            <div class="flex-1 overflow-y-auto">
                <!-- Admin Menu (default) -->
                <div id="admin-menu" class="menu-section">
                    <div class="p-4 text-blue-200 text-xs uppercase font-bold tracking-wider">Principal</div>
                    <a href="#" class="menu-item block py-3 px-4 bg-blue-700/50 flex items-center transition-all duration-200" data-tab="dashboard-admin">
                        <i class="fas fa-tachometer-alt mr-3 text-lg"></i>
                        <span class="menu-text">Dashboard</span>
                    </a>

                    <div class="p-4 text-blue-200 text-xs uppercase font-bold tracking-wider">Módulos Principales</div>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="parametros">
                        <i class="fas fa-sliders-h mr-3 text-lg"></i>
                        <span class="menu-text">Parámetros</span>
                    </a>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="logica">
                        <i class="fas fa-brain mr-3 text-lg"></i>
                        <span class="menu-text">Lógica</span>
                    </a>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="registros">
                        <i class="fas fa-clipboard-list mr-3 text-lg"></i>
                        <span class="menu-text">Registros</span>
                    </a>

                    <div class="p-4 text-blue-200 text-xs uppercase font-bold tracking-wider">Módulos Secundarios</div>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="conductores">
                        <i class="fas fa-id-card-alt mr-3 text-lg"></i>
                        <span class="menu-text">Conductores</span>
                    </a>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="anfitriones">
                        <i class="fas fa-user-tie mr-3 text-lg"></i>
                        <span class="menu-text">Anfitriones</span>
                    </a>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="buses">
                        <i class="fas fa-bus mr-3 text-lg"></i>
                        <span class="menu-text">Buses</span>
                    </a>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="cambios">
                        <i class="fas fa-exchange-alt mr-3 text-lg"></i>
                        <span class="menu-text">Cambios</span>
                    </a>

                    <div class="p-4 text-blue-200 text-xs uppercase font-bold tracking-wider">Módulos Terciarios</div>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="descansos">
                        <i class="fas fa-bed mr-3 text-lg"></i>
                        <span class="menu-text">Descansos</span>
                    </a>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="condicion">
                        <i class="fas fa-heartbeat mr-3 text-lg"></i>
                        <span class="menu-text">Condición Diaria</span>
                    </a>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="subempresa">
                        <i class="fas fa-building mr-3 text-lg"></i>
                        <span class="menu-text">Subempresa</span>
                    </a>

                    <div class="p-4 text-blue-200 text-xs uppercase font-bold tracking-wider">Módulos Cuartos</div>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="planificacion">
                        <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                        <span class="menu-text">Planificación</span>
                    </a>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="replanificacion">
                        <i class="fas fa-redo mr-3 text-lg"></i>
                        <span class="menu-text">Replanificación</span>
                    </a>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="descargas">
                        <i class="fas fa-download mr-3 text-lg"></i>
                        <span class="menu-text">Descargas</span>
                    </a>
                </div>

                <!-- Other role menus -->
                <div id="planner-menu" class="menu-section hidden">
                    <div class="p-4 text-blue-200 text-xs uppercase font-bold tracking-wider">Principal</div>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="dashboard-planner">
                        <i class="fas fa-tachometer-alt mr-3 text-lg"></i>
                        <span class="menu-text">Dashboard</span>
                    </a>
                    <div class="p-4 text-blue-200 text-xs uppercase font-bold tracking-wider">Módulos Principales</div>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="parametros">
                        <i class="fas fa-sliders-h mr-3 text-lg"></i>
                        <span class="menu-text">Parámetros</span>
                    </a>
                    <div class="p-4 text-blue-200 text-xs uppercase font-bold tracking-wider">Módulos Cuartos</div>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="planificacion">
                        <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                        <span class="menu-text">Planificación</span>
                    </a>
                </div>

                <div id="programmer-menu" class="menu-section hidden">
                    <div class="p-4 text-blue-200 text-xs uppercase font-bold tracking-wider">Principal</div>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="dashboard-programmer">
                        <i class="fas fa-tachometer-alt mr-3 text-lg"></i>
                        <span class="menu-text">Dashboard</span>
                    </a>
                    <div class="p-4 text-blue-200 text-xs uppercase font-bold tracking-wider">Módulos Secundarios</div>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="conductores">
                        <i class="fas fa-id-card-alt mr-3 text-lg"></i>
                        <span class="menu-text">Conductores</span>
                    </a>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="buses">
                        <i class="fas fa-bus mr-3 text-lg"></i>
                        <span class="menu-text">Buses</span>
                    </a>
                </div>

                <div id="operator-menu" class="menu-section hidden">
                    <div class="p-4 text-blue-200 text-xs uppercase font-bold tracking-wider">Principal</div>
                    <a href="#" class="menu-item block py-3 px-4 hover:bg-blue-700/50 flex items-center transition-all duration-200" data-tab="dashboard-operator">
                        <i class="fas fa-tachometer-alt mr-3 text-lg"></i>
                        <span class="menu-text">Dashboard</span>
                    </a>
                </div>
            </div>

            <!-- Collapse Button -->
            <div class="p-4 border-t border-blue-700/50 flex justify-center">
                <button id="sidebar-toggle" class="text-blue-200 hover:text-white transition-colors duration-200">
                    <i class="fas fa-chevron-left text-lg"></i>
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div id="content-area" class="content-area flex-1 flex flex-col overflow-hidden ml-64">
            <!-- Top Navigation -->
            <header class="bg-white/95 backdrop-blur-sm shadow-lg border-b border-gray-200 py-4 px-6 flex items-center justify-between">
                <div class="flex items-center">
                    <button id="mobile-menu-toggle" class="text-gray-500 mr-4 md:hidden">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800" id="page-title">Dashboard Administrador</h1>
                        <p class="text-sm text-gray-500" id="page-subtitle">Panel de control principal</p>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Real-time clock -->
                    <div class="hidden md:flex flex-col items-end text-sm">
                        <div id="current-time" class="font-medium text-gray-700"></div>
                        <div id="current-date" class="text-xs text-gray-500"></div>
                    </div>

                    <!-- Notifications -->
                    <div class="relative">
                        <button id="notifications-btn" class="relative text-gray-500 hover:text-gray-700 transition-colors duration-200">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="notification-badge absolute -top-1 -right-1 h-3 w-3 rounded-full bg-red-500"></span>
                        </button>
                        <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl py-2 z-50 border">
                            <div class="px-4 py-3 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-800">Notificaciones</h3>
                                <p class="text-sm text-gray-500">3 nuevas notificaciones</p>
                            </div>
                            <div class="max-h-80 overflow-y-auto" id="notifications-list">
                                <!-- Las notificaciones se cargan dinámicamente -->
                            </div>
                            <div class="px-4 py-2 border-t border-gray-200">
                                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Ver todas las notificaciones</a>
                            </div>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="relative">
                        <button id="user-menu-btn" class="flex items-center text-sm focus:outline-none hover:bg-gray-100 rounded-lg p-2 transition-colors duration-200">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center text-white">
                                <i class="fas fa-user"></i>
                            </div>
                            <span class="ml-2 hidden md:inline font-medium">Admin User</span>
                            <i class="fas fa-chevron-down ml-1 text-gray-500 text-xs hidden md:inline"></i>
                        </button>
                        <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-1 z-50 border">
                            <div class="px-4 py-3 border-b border-gray-200">
                                <p class="text-sm font-medium text-gray-800">Admin User</p>
                                <p class="text-xs text-gray-500">administrador@empresa.com</p>
                            </div>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                                <i class="fas fa-user mr-2"></i> Mi Perfil
                            </a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                                <i class="fas fa-cog mr-2"></i> Configuración
                            </a>
                            <div class="border-t border-gray-200"></div>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                                <i class="fas fa-sign-out-alt mr-2"></i> Cerrar Sesión
                            </a>
                        </div>
                    </div>

                    <!-- Role Switch -->
                    <div class="relative">
                        <button id="role-switch-btn" class="flex items-center text-sm focus:outline-none glass-effect px-3 py-2 rounded-lg text-blue-800 transition-all duration-200">
                            <i class="fas fa-user-tag mr-2"></i>
                            <span>Administrador</span>
                            <i class="fas fa-chevron-down ml-1 text-xs"></i>
                        </button>
                        <div id="role-switch-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-1 z-50 border">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200" data-role="admin">
                                <i class="fas fa-crown mr-2 text-yellow-500"></i> Administrador
                            </a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200" data-role="planner">
                                <i class="fas fa-calendar-alt mr-2 text-blue-500"></i> Planificador
                            </a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200" data-role="programmer">
                                <i class="fas fa-code mr-2 text-purple-500"></i> Programador
                            </a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200" data-role="operator">
                                <i class="fas fa-headset mr-2 text-red-500"></i> Operador
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto p-6 bg-gradient-to-br from-gray-50 to-blue-50">
                <!-- Dashboard Admin -->
                <div id="dashboard-admin" class="tab-content active">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">Dashboard Administrador</h2>
                        <p class="text-gray-600">Panel completo de control y gestión del sistema</p>
                    </div>

                    <!-- KPI Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="metric-card dashboard-card text-white rounded-xl shadow-lg p-6 relative overflow-hidden">
                            <div class="flex items-center justify-between relative z-10">
                                <div>
                                    <p class="text-blue-100 text-sm font-medium">Conductores Activos</p>
                                    <h3 class="text-3xl font-bold text-white mt-1" id="metric-conductores">142</h3>
                                    <div class="flex items-center mt-2">
                                        <i class="fas fa-arrow-up text-green-300 text-sm mr-1"></i>
                                        <span class="text-green-300 text-sm font-medium">+5% desde ayer</span>
                                    </div>
                                </div>
                                <div class="p-3 rounded-full bg-white/20 backdrop-blur-sm">
                                    <i class="fas fa-users text-2xl text-white"></i>
                                </div>
                            </div>
                        </div>

                        <div class="metric-card bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-xl shadow-lg p-6 relative overflow-hidden">
                            <div class="flex items-center justify-between relative z-10">
                                <div>
                                    <p class="text-red-100 text-sm font-medium">Validaciones Pendientes</p>
                                    <h3 class="text-3xl font-bold text-white mt-1" id="metric-validaciones">8</h3>
                                    <div class="flex items-center mt-2">
                                        <i class="fas fa-arrow-up text-red-200 text-sm mr-1"></i>
                                        <span class="text-red-200 text-sm font-medium">+2 desde ayer</span>
                                    </div>
                                </div>
                                <div class="p-3 rounded-full bg-white/20 backdrop-blur-sm">
                                    <i class="fas fa-exclamation-triangle text-2xl text-white"></i>
                                </div>
                            </div>
                        </div>

                        <div class="metric-card bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl shadow-lg p-6 relative overflow-hidden">
                            <div class="flex items-center justify-between relative z-10">
                                <div>
                                    <p class="text-green-100 text-sm font-medium">Rutas Programadas</p>
                                    <h3 class="text-3xl font-bold text-white mt-1" id="metric-rutas">56</h3>
                                    <div class="flex items-center mt-2">
                                        <i class="fas fa-arrow-up text-green-200 text-sm mr-1"></i>
                                        <span class="text-green-200 text-sm font-medium">+12% semanal</span>
                                    </div>
                                </div>
                                <div class="p-3 rounded-full bg-white/20 backdrop-blur-sm">
                                    <i class="fas fa-route text-2xl text-white"></i>
                                </div>
                            </div>
                        </div>

                        <div class="metric-card bg-gradient-to-r from-yellow-500 to-orange-600 text-white rounded-xl shadow-lg p-6 relative overflow-hidden">
                            <div class="flex items-center justify-between relative z-10">
                                <div>
                                    <p class="text-yellow-100 text-sm font-medium">Eficiencia Promedio</p>
                                    <h3 class="text-3xl font-bold text-white mt-1" id="metric-eficiencia">87%</h3>
                                    <div class="flex items-center mt-2">
                                        <i class="fas fa-arrow-up text-yellow-200 text-sm mr-1"></i>
                                        <span class="text-yellow-200 text-sm font-medium">+2% desde ayer</span>
                                    </div>
                                </div>
                                <div class="p-3 rounded-full bg-white/20 backdrop-blur-sm">
                                    <i class="fas fa-chart-line text-2xl text-white"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts and Activity -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                        <!-- Activity Recent -->
                        <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-xl font-bold text-gray-800">Actividad Reciente</h3>
                                <a href="#" class="text-blue-600 hover:text-blue-800 font-medium transition-colors duration-200">Ver todo</a>
                            </div>
                            <div class="space-y-4" id="recent-activities">
                                <!-- Se cargan dinámicamente -->
                            </div>
                        </div>

                        <!-- Critical Validations -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-xl font-bold text-gray-800">Validaciones Críticas</h3>
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-medium" id="critical-count">8 Pendientes</span>
                            </div>
                            <div class="space-y-4" id="critical-validations">
                                <!-- Se cargan dinámicamente -->
                            </div>
                        </div>
                    </div>

                    <!-- KPIs Section -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-gray-800">KPIs de Medición</h3>
                            <div class="flex space-x-2">
                                <button class="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">Semanal</button>
                                <button class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg">Mensual</button>
                                <button class="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">Anual</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6" id="kpis-grid">
                            <!-- Se cargan dinámicamente -->
                        </div>
                    </div>
                </div>

                <!-- Dashboard Planner -->
                <div id="dashboard-planner" class="tab-content">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">Dashboard Planificador</h2>
                        <p class="text-gray-600">Panel de control para planificación de rutas y turnos</p>
                    </div>

                    <!-- Planner KPIs -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Turnos Planificados Hoy</p>
                                    <h3 class="text-2xl font-bold text-green-600" id="planner-turnos">156</h3>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full">
                                    <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs text-green-600 font-medium">+8% desde ayer</p>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Cobertura Semanal</p>
                                    <h3 class="text-2xl font-bold text-blue-600" id="planner-cobertura">98%</h3>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-full">
                                    <i class="fas fa-chart-pie text-blue-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs text-blue-600 font-medium">Objetivo: 95%</p>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Turnos sin Asignar</p>
                                    <h3 class="text-2xl font-bold text-yellow-600" id="planner-pendientes">12</h3>
                                </div>
                                <div class="p-3 bg-yellow-100 rounded-full">
                                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs text-yellow-600 font-medium">Para mañana</p>
                        </div>
                    </div>

                    <!-- Quick Actions for Planner -->
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Acciones Rápidas</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <button onclick="showPlanningModal()" class="p-4 bg-blue-50 hover:bg-blue-100 rounded-lg border-2 border-blue-200 transition-colors duration-200">
                                <i class="fas fa-calendar-plus text-blue-600 text-2xl mb-2"></i>
                                <div class="font-medium text-gray-800">Nueva Planificación</div>
                                <div class="text-sm text-gray-600">Crear planificación semanal</div>
                            </button>

                            <button onclick="executeAlgorithm()" class="p-4 bg-green-50 hover:bg-green-100 rounded-lg border-2 border-green-200 transition-colors duration-200">
                                <i class="fas fa-robot text-green-600 text-2xl mb-2"></i>
                                <div class="font-medium text-gray-800">Algoritmo Automático</div>
                                <div class="text-sm text-gray-600">Ejecutar planificación IA</div>
                            </button>

                            <button onclick="showReportsModal()" class="p-4 bg-purple-50 hover:bg-purple-100 rounded-lg border-2 border-purple-200 transition-colors duration-200">
                                <i class="fas fa-chart-bar text-purple-600 text-2xl mb-2"></i>
                                <div class="font-medium text-gray-800">Reportes</div>
                                <div class="text-sm text-gray-600">Ver métricas y análisis</div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Programmer -->
                <div id="dashboard-programmer" class="tab-content">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">Dashboard Programador</h2>
                        <p class="text-gray-600">Control operativo de conductores y asignaciones</p>
                    </div>

                    <!-- Programmer KPIs -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Disponibles</p>
                                    <h3 class="text-2xl font-bold text-green-600" id="prog-disponibles">42</h3>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full">
                                    <i class="fas fa-user-check text-green-600 text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">En Ruta</p>
                                    <h3 class="text-2xl font-bold text-blue-600" id="prog-enruta">28</h3>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-full">
                                    <i class="fas fa-route text-blue-600 text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Tardanzas</p>
                                    <h3 class="text-2xl font-bold text-yellow-600" id="prog-tardanzas">3</h3>
                                </div>
                                <div class="p-3 bg-yellow-100 rounded-full">
                                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Incidencias</p>
                                    <h3 class="text-2xl font-bold text-red-600" id="prog-incidencias">1</h3>
                                </div>
                                <div class="p-3 bg-red-100 rounded-full">
                                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Real-time Operations -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Operaciones en Tiempo Real</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conductor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruta Actual</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hora Salida</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Próximo Turno</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="operations-table">
                                    <!-- Se carga dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Operator -->
                <div id="dashboard-operator" class="tab-content">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">Dashboard Operador</h2>
                        <p class="text-gray-600">Monitoreo y control operativo en tiempo real</p>
                    </div>

                    <!-- Emergency Alert Button -->
                    <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl shadow-lg p-6 mb-8 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xl font-bold mb-2">Centro de Alertas</h3>
                                <p class="text-red-100">Envía alertas inmediatas al equipo de programación</p>
                            </div>
                            <button onclick="showAlertModal()" class="bg-white text-red-600 px-6 py-3 rounded-lg font-bold hover:bg-red-50 transition-colors duration-200">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                ENVIAR ALERTA
                            </button>
                        </div>
                    </div>

                    <!-- Operator Planning View -->
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-gray-800">Plantilla Operativa - Control de Turnos</h3>
                            <div class="flex space-x-2">
                                <button onclick="refreshOperatorView()" class="px-3 py-2 text-sm bg-green-500 text-white rounded-lg hover:bg-green-600">
                                    <i class="fas fa-sync-alt mr-1"></i>Actualizar
                                </button>
                                <button onclick="showChangesHistory()" class="px-3 py-2 text-sm bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                                    <i class="fas fa-history mr-1"></i>Historial
                                </button>
                            </div>
                        </div>

                        <!-- Operator Filters -->
                        <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <div>
                                    <select id="operator-day-filter" onchange="filterOperatorView()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        <option value="all">Todos los días</option>
                                        <option value="today">Solo hoy</option>
                                        <option value="tomorrow">Solo mañana</option>
                                    </select>
                                </div>
                                <div>
                                    <select id="operator-status-filter" onchange="filterOperatorView()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        <option value="all">Todos los estados</option>
                                        <option value="active">En ruta</option>
                                        <option value="scheduled">Programado</option>
                                        <option value="issues">Con problemas</option>
                                    </select>
                                </div>
                                <div>
                                    <select id="operator-route-filter" onchange="filterOperatorView()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        <option value="all">Todas las rutas</option>
                                        <option value="lima-nazca">Lima-Nazca</option>
                                        <option value="lima-ica">Lima-Ica</option>
                                        <option value="lima-pisco">Lima-Pisco</option>
                                        <option value="lima-chincha">Lima-Chincha</option>
                                    </select>
                                </div>
                                <div>
                                    <input type="text" id="operator-search" onkeyup="searchOperatorView()" placeholder="Buscar conductor..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Operator Grid with Clickable Routes -->
                        <div class="operator-planning-grid" id="operator-planning-grid">
                            <!-- Se genera dinámicamente -->
                        </div>

                        <!-- Quick Stats for Operator -->
                        <div class="mt-6 grid grid-cols-4 gap-4 text-center">
                            <div class="p-3 bg-green-50 rounded-lg">
                                <div class="text-2xl font-bold text-green-600" id="op-stats-active">24</div>
                                <div class="text-sm text-green-700">En Ruta</div>
                            </div>
                            <div class="p-3 bg-blue-50 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600" id="op-stats-scheduled">18</div>
                                <div class="text-sm text-blue-700">Programados</div>
                            </div>
                            <div class="p-3 bg-yellow-50 rounded-lg">
                                <div class="text-2xl font-bold text-yellow-600" id="op-stats-pending">3</div>
                                <div class="text-sm text-yellow-700">Cambios Pendientes</div>
                            </div>
                            <div class="p-3 bg-red-50 rounded-lg">
                                <div class="text-2xl font-bold text-red-600" id="op-stats-issues">2</div>
                                <div class="text-sm text-red-700">Con Problemas</div>
                            </div>
                        </div>
                    </div>

                    <!-- Real-time Status Monitor -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-4">Monitor en Tiempo Real</h3>
                            <div class="space-y-3" id="realtime-monitor">
                                <!-- Se cargan dinámicamente -->
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-4">Alertas y Notificaciones</h3>
                            <div class="space-y-3" id="operator-alerts">
                                <!-- Se cargan dinámicamente -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Planificación Module -->
                <div id="planificacion" class="tab-content">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">Módulo de Planificación</h2>
                        <p class="text-gray-600">Gestión inteligente de asignación de turnos múltiples por conductor</p>
                    </div>

                    <!-- Planning Controls -->
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-gray-800">Control de Planificación</h3>
                            <div class="flex space-x-3">
                                <button onclick="executeAlgorithm()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors duration-200">
                                    <i class="fas fa-play mr-2"></i> Ejecutar Algoritmo
                                </button>
                                <button onclick="savePlanning()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors duration-200">
                                    <i class="fas fa-save mr-2"></i> Guardar Planificación
                                </button>
                                <button onclick="exportPlanning()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors duration-200">
                                    <i class="fas fa-download mr-2"></i> Exportar
                                </button>
                            </div>
                        </div>

                        <!-- Planning Configuration -->
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                            <div>
                                <label class="block text-gray-700 text-sm font-medium mb-2">Fecha Inicio</label>
                                <input type="date" id="planning-start" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" value="2024-01-15">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-medium mb-2">Fecha Fin</label>
                                <input type="date" id="planning-end" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" value="2024-01-21">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-medium mb-2">Tipo Planificación</label>
                                <select id="planning-type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="semanal">Semanal</option>
                                    <option value="3dias">3 Días</option>
                                    <option value="diaria">Diaria</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-medium mb-2">Algoritmo</label>
                                <select id="algorithm-type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="ia">Optimización IA</option>
                                    <option value="antiguedad">Por Antigüedad</option>
                                    <option value="eficiencia">Por Eficiencia</option>
                                    <option value="manual">Asignación Manual</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-medium mb-2">Plantilla Base</label>
                                <select id="template-type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="mixta">Mixta (1L+2C / 2L / 3C)</option>
                                    <option value="largas">Solo Rutas Largas</option>
                                    <option value="cortas">Solo Rutas Cortas</option>
                                    <option value="personalizada">Personalizada</option>
                                </select>
                            </div>
                        </div>

                        <!-- Algorithm Status -->
                        <div id="algorithm-status" class="hidden mb-6">
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                                <div class="flex items-center">
                                    <div class="loading-spinner mr-3"></div>
                                    <div>
                                        <h4 class="font-medium text-blue-800">Ejecutando algoritmo de planificación...</h4>
                                        <p class="text-blue-600 text-sm" id="algorithm-progress">Analizando disponibilidad de conductores...</p>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="progress-bar">
                                        <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                                    </div>
                                    <p class="text-xs text-blue-600 mt-1">Progreso: <span id="progress-text">0%</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Planning Grid with Filters and Sorting -->
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-gray-800">Planificación Semanal - Turnos Múltiples</h3>
                            <div class="flex space-x-2">
                                <button onclick="previousWeek()" class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                    <i class="fas fa-chevron-left mr-1"></i>Anterior
                                </button>
                                <button class="px-3 py-1 text-sm bg-blue-600 text-white rounded-lg" id="current-week">15-21 Enero 2024</button>
                                <button onclick="nextWeek()" class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                    Siguiente<i class="fas fa-chevron-right ml-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Advanced Filters and Controls -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Filtrar por Conductor</label>
                                    <select id="conductor-filter" onchange="filterPlanningGrid()" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Todos los conductores</option>
                                        <option value="C001">Juan Pérez (#C001)</option>
                                        <option value="C002">Carlos Gómez (#C002)</option>
                                        <option value="C003">Luis Ramírez (#C003)</option>
                                        <option value="C004">Miguel Torres (#C004)</option>
                                        <option value="C005">Roberto Silva (#C005)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Ordenar por Tramos</label>
                                    <select id="tramo-sort" onchange="sortPlanningGrid()" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="horario">Por Horario (Ascendente)</option>
                                        <option value="horario-desc">Por Horario (Descendente)</option>
                                        <option value="ruta">Por Ruta Alfabético</option>
                                        <option value="duracion">Por Duración</option>
                                        <option value="tipo">Por Tipo (Larga/Corta)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Vista de Frecuencias</label>
                                    <select id="frequency-view" onchange="toggleFrequencyView()" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="normal">Vista Normal</option>
                                        <option value="frequency">Orden por Frecuencias</option>
                                        <option value="timeline">Vista Timeline</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Acciones</label>
                                    <div class="flex space-x-2">
                                        <button onclick="resetFilters()" class="px-3 py-2 bg-gray-500 text-white text-sm rounded-lg hover:bg-gray-600">
                                            <i class="fas fa-undo mr-1"></i>Reset
                                        </button>
                                        <button onclick="toggleDetailView()" class="px-3 py-2 bg-blue-500 text-white text-sm rounded-lg hover:bg-blue-600">
                                            <i class="fas fa-eye mr-1"></i>Detalle
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Frequency Summary -->
                            <div id="frequency-summary" class="mt-4 p-3 bg-white rounded border">
                                <h4 class="font-medium text-gray-800 mb-2">Resumen de Frecuencias de Horarios</h4>
                                <div class="grid grid-cols-6 gap-2 text-xs" id="frequency-chart">
                                    <!-- Se llena dinámicamente -->
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Legend with Statistics -->
                        <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-sm font-medium text-gray-700">Leyenda de Turnos:</div>
                                <div class="text-xs text-gray-500" id="planning-stats">
                                    Total Turnos: <span class="font-medium">0</span> |
                                    Rutas Largas: <span class="font-medium">0</span> |
                                    Rutas Cortas: <span class="font-medium">0</span> |
                                    Descansos: <span class="font-medium">0</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-4 text-xs">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-blue-200 border border-blue-300 rounded mr-2"></div>
                                    <span>Ruta Larga (L) - 6+ horas</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-green-200 border border-green-300 rounded mr-2"></div>
                                    <span>Ruta Corta (C) - 2-4 horas</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-yellow-200 border border-yellow-300 rounded mr-2"></div>
                                    <span>Múltiples Turnos (M)</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-gray-200 border border-gray-300 rounded mr-2"></div>
                                    <span>Descanso (D)</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-red-200 border border-red-300 rounded mr-2"></div>
                                    <span>Crítico - Requiere atención</span>
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Planning Grid -->
                        <div class="planning-grid" id="planning-grid-enhanced">
                            <!-- Grid se genera dinámicamente con más detalles -->
                        </div>

                        <!-- Detailed Timeline View (Hidden by default) -->
                        <div id="timeline-view" class="hidden mt-6">
                            <h4 class="text-lg font-medium text-gray-800 mb-4">Vista Timeline - Orden por Frecuencias de Horas</h4>
                            <div class="space-y-4" id="timeline-container">
                                <!-- Timeline se genera dinámicamente -->
                            </div>
                        </div>
                    </div>

                    <!-- Planning Metrics -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-4">Métricas de Planificación</h3>
                            <div class="space-y-6" id="planning-metrics">
                                <!-- Se cargan dinámicamente -->
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-4">Estado del Algoritmo</h3>
                            <div class="space-y-4" id="algorithm-details">
                                <!-- Se cargan dinámicamente -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Parametros Module -->
                <div id="parametros" class="tab-content">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">Módulo de Parámetros</h2>
                        <p class="text-gray-600">Configuración de parámetros del sistema y algoritmos</p>
                    </div>

                    <!-- Parameters Management -->
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-gray-800">Gestión de Parámetros</h3>
                            <div class="flex space-x-3">
                                <button onclick="importParameters()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors duration-200">
                                    <i class="fas fa-upload mr-2"></i> Importar CSV
                                </button>
                                <button onclick="addParameter()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors duration-200">
                                    <i class="fas fa-plus mr-2"></i> Nuevo Parámetro
                                </button>
                            </div>
                        </div>

                        <!-- Search and Filter -->
                        <div class="mb-6">
                            <div class="flex space-x-4">
                                <div class="flex-1">
                                    <input type="text" id="param-search" placeholder="Buscar parámetro..." class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <button onclick="searchParameters()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg flex items-center transition-colors duration-200">
                                    <i class="fas fa-search mr-2"></i> Buscar
                                </button>
                            </div>
                        </div>

                        <!-- Parameters Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Campo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="parameters-table">
                                    <!-- Se cargan dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Parameter Editor -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">Editor de Parámetros</h3>
                        <form id="parameter-form" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-gray-700 text-sm font-medium mb-2">Nombre del Campo</label>
                                    <input type="text" id="param-name" placeholder="Ej: ORIGEN_DISP" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-medium mb-2">Tipo de Dato</label>
                                    <select id="param-type" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="string">String</option>
                                        <option value="integer">Integer</option>
                                        <option value="float">Float</option>
                                        <option value="boolean">Boolean</option>
                                        <option value="array">Array</option>
                                        <option value="date">Date</option>
                                        <option value="datetime">DateTime</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-medium mb-2">Valor</label>
                                <textarea id="param-value" rows="3" placeholder="Ingrese el valor del parámetro" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-medium mb-2">Descripción</label>
                                <textarea id="param-description" rows="2" placeholder="Descripción del parámetro y su uso en el sistema" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>

                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="cancelParameterEdit()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-6 rounded-lg transition-colors duration-200">
                                    Cancelar
                                </button>
                                <button type="button" onclick="validateParameter()" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded-lg transition-colors duration-200">
                                    Validar
                                </button>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition-colors duration-200">
                                    Guardar Parámetro
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Conductores Module -->
                <div id="conductores" class="tab-content">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">Módulo de Conductores</h2>
                        <p class="text-gray-600">Gestión integral de conductores y sus asignaciones</p>
                    </div>

                    <!-- Driver Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Disponibles</p>
                                    <h3 class="text-2xl font-bold text-green-600" id="drivers-available">42</h3>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full">
                                    <i class="fas fa-user-check text-green-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs text-green-600 font-medium">+2 desde ayer</p>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">En Descanso</p>
                                    <h3 class="text-2xl font-bold text-yellow-600" id="drivers-rest">18</h3>
                                </div>
                                <div class="p-3 bg-yellow-100 rounded-full">
                                    <i class="fas fa-bed text-yellow-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs text-yellow-600 font-medium">Normal</p>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">En Vacaciones</p>
                                    <h3 class="text-2xl font-bold text-blue-600" id="drivers-vacation">8</h3>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-full">
                                    <i class="fas fa-umbrella-beach text-blue-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs text-blue-600 font-medium">-1 desde ayer</p>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Críticos</p>
                                    <h3 class="text-2xl font-bold text-red-600" id="drivers-critical">4</h3>
                                </div>
                                <div class="p-3 bg-red-100 rounded-full">
                                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs text-red-600 font-medium">Requieren atención</p>
                        </div>
                    </div>

                    <!-- Driver Management -->
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-gray-800">Gestión de Conductores</h3>
                            <div class="flex space-x-3">
                                <button onclick="addDriver()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors duration-200">
                                    <i class="fas fa-plus mr-2"></i> Nuevo Conductor
                                </button>
                                <button onclick="importDrivers()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors duration-200">
                                    <i class="fas fa-upload mr-2"></i> Importar CSV
                                </button>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                            <div>
                                <input type="text" id="driver-search" placeholder="Buscar conductor..." class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <select id="status-filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Todos los estados</option>
                                    <option value="disponible">Disponible</option>
                                    <option value="descanso">Descanso</option>
                                    <option value="vacaciones">Vacaciones</option>
                                    <option value="suspendido">Suspendido</option>
                                </select>
                            </div>
                            <div>
                                <select id="origin-filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Todos los orígenes</option>
                                    <option value="lima">Lima</option>
                                    <option value="chincha">Chincha</option>
                                    <option value="ica">Ica</option>
                                    <option value="nazca">Nazca</option>
                                    <option value="pisco">Pisco</option>
                                </select>
                            </div>
                            <div>
                                <select id="service-filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Todos los servicios</option>
                                    <option value="estandar">Estándar</option>
                                    <option value="surbus">Surbus</option>
                                    <option value="nazca">Nazca</option>
                                </select>
                            </div>
                            <div>
                                <select id="efficiency-filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Toda eficiencia</option>
                                    <option value="high">Alta (>90%)</option>
                                    <option value="medium">Media (80-90%)</option>
                                    <option value="low">Baja (<80%)</option>
                                </select>
                            </div>
                            <div>
                                <button onclick="filterDrivers()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg flex items-center justify-center transition-colors duration-200">
                                    <i class="fas fa-filter mr-2"></i> Filtrar
                                </button>
                            </div>
                        </div>

                        <!-- Drivers Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conductor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origen</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Servicio</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eficiencia</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="drivers-table">
                                    <!-- Se cargan dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Buses Module -->
                <div id="buses" class="tab-content">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">Módulo de Buses</h2>
                        <p class="text-gray-600">Gestión de flota de buses y mantenimiento</p>
                    </div>

                    <!-- Bus Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Operativos</p>
                                    <h3 class="text-2xl font-bold text-green-600" id="buses-operational">38</h3>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full">
                                    <i class="fas fa-bus text-green-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Mantenimiento</p>
                                    <h3 class="text-2xl font-bold text-yellow-600" id="buses-maintenance">5</h3>
                                </div>
                                <div class="p-3 bg-yellow-100 rounded-full">
                                    <i class="fas fa-tools text-yellow-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Reparación</p>
                                    <h3 class="text-2xl font-bold text-red-600" id="buses-repair">2</h3>
                                </div>
                                <div class="p-3 bg-red-100 rounded-full">
                                    <i class="fas fa-wrench text-red-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm font-medium">Disponibilidad</p>
                                    <h3 class="text-2xl font-bold text-blue-600" id="buses-availability">84%</h3>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-full">
                                    <i class="fas fa-chart-pie text-blue-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bus Management -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-gray-800">Gestión de Buses</h3>
                            <div class="flex space-x-3">
                                <button onclick="addBus()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors duration-200">
                                    <i class="fas fa-plus mr-2"></i> Nuevo Bus
                                </button>
                                <button onclick="scheduleMaintenance()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors duration-200">
                                    <i class="fas fa-calendar mr-2"></i> Programar Mantenimiento
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Placa</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modelo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacidad</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kilometraje</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Próximo Mantenimiento</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="buses-table">
                                    <!-- Se cargan dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Otros módulos se cargan de manera similar -->

            </main>
        </div>
    </div>

    <!-- Modals -->

    <!-- Planning Modal -->
    <div id="planning-modal" class="modal">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-2xl w-full mx-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-800">Nueva Planificación</h3>
                <button onclick="closePlanningModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Conductor</label>
                        <select class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option>Seleccionar conductor...</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fecha</label>
                        <input type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Configuración de Turnos</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button class="p-3 border-2 border-blue-200 rounded-lg hover:bg-blue-50 text-sm">
                            <div class="font-medium">1 Larga + 2 Cortas</div>
                            <div class="text-xs text-gray-500">Turno mixto</div>
                        </button>
                        <button class="p-3 border-2 border-green-200 rounded-lg hover:bg-green-50 text-sm">
                            <div class="font-medium">2 Rutas Largas</div>
                            <div class="text-xs text-gray-500">Día intensivo</div>
                        </button>
                        <button class="p-3 border-2 border-yellow-200 rounded-lg hover:bg-yellow-50 text-sm">
                            <div class="font-medium">3 Rutas Cortas</div>
                            <div class="text-xs text-gray-500">Día ligero</div>
                        </button>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 pt-4">
                    <button onclick="closePlanningModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button onclick="savePlanningModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Guardar Planificación
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Route Details and Change Request Modal -->
    <div id="route-details-modal" class="modal">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-800">Detalles de Turno - Solicitud de Cambio</h3>
                <button onclick="closeRouteDetailsModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Current Route Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-800 mb-3">Información Actual</h4>
                    <div class="space-y-2 text-sm">
                        <div><span class="font-medium">Conductor:</span> <span id="modal-conductor">-</span></div>
                        <div><span class="font-medium">Fecha:</span> <span id="modal-date">-</span></div>
                        <div><span class="font-medium">Ruta:</span> <span id="modal-route">-</span></div>
                        <div><span class="font-medium">Horario:</span> <span id="modal-time">-</span></div>
                        <div><span class="font-medium">Tipo:</span> <span id="modal-type">-</span></div>
                        <div><span class="font-medium">Bus Asignado:</span> <span id="modal-bus">-</span></div>
                        <div><span class="font-medium">Estado:</span> <span id="modal-status">-</span></div>
                    </div>
                </div>

                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-800 mb-3">Estadísticas del Conductor</h4>
                    <div class="space-y-2 text-sm">
                        <div><span class="font-medium">Eficiencia:</span> <span id="modal-efficiency">-</span></div>
                        <div><span class="font-medium">Puntualidad:</span> <span id="modal-punctuality">-</span></div>
                        <div><span class="font-medium">Días trabajados:</span> <span id="modal-days-worked">-</span></div>
                        <div><span class="font-medium">Último descanso:</span> <span id="modal-last-rest">-</span></div>
                        <div><span class="font-medium">Rutas cortas esta semana:</span> <span id="modal-short-routes">-</span></div>
                    </div>
                </div>
            </div>

            <!-- Change Request Form -->
            <form id="change-request-form" class="space-y-6">
                <div class="border-t pt-6">
                    <h4 class="font-medium text-gray-800 mb-4">Solicitar Cambio</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Cambio</label>
                            <select id="change-type" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                                <option value="">Seleccionar tipo de cambio...</option>
                                <option value="conductor">Cambio de Conductor</option>
                                <option value="horario">Cambio de Horario</option>
                                <option value="ruta">Cambio de Ruta</option>
                                <option value="bus">Cambio de Bus</option>
                                <option value="cancelacion">Cancelación de Turno</option>
                                <option value="urgente">Cambio Urgente</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad</label>
                            <select id="change-priority" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                                <option value="baja">Baja - Planificar para siguiente turno</option>
                                <option value="media">Media - Implementar hoy</option>
                                <option value="alta">Alta - Urgente (implementar inmediatamente)</option>
                                <option value="critica">Crítica - Emergencia operativa</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Motivo del Cambio</label>
                        <select id="change-reason" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                            <option value="">Seleccionar motivo...</option>
                            <option value="falta">Falta del conductor</option>
                            <option value="tardanza">Tardanza del conductor</option>
                            <option value="enfermedad">Enfermedad/Salud</option>
                            <option value="bus-problema">Problema mecánico del bus</option>
                            <option value="trafico">Problema de tráfico/ruta</option>
                            <option value="emergencia">Emergencia familiar</option>
                            <option value="judicial">Tema judicial/legal</option>
                            <option value="optimizacion">Optimización operativa</option>
                            <option value="otro">Otro motivo</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Descripción Detallada</label>
                        <textarea id="change-description" rows="3"
                            placeholder="Describa en detalle el motivo del cambio, las condiciones actuales y la solución propuesta..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2" required></textarea>
                    </div>

                    <!-- Proposed Changes -->
                    <div class="bg-yellow-50 p-4 rounded-lg mb-4">
                        <h5 class="font-medium text-gray-800 mb-3">Cambios Propuestos</h5>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nuevo Conductor (si aplica)</label>
                                <select id="new-conductor" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="">Mantener conductor actual</option>
                                    <option value="C001">Juan Pérez (#C001)</option>
                                    <option value="C002">Carlos Gómez (#C002)</option>
                                    <option value="C003">Luis Ramírez (#C003)</option>
                                    <option value="C004">Miguel Torres (#C004)</option>
                                    <option value="C005">Roberto Silva (#C005)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nueva Hora (si aplica)</label>
                                <input type="time" id="new-time" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nueva Ruta (si aplica)</label>
                                <select id="new-route" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="">Mantener ruta actual</option>
                                    <option value="lima-nazca">Lima - Nazca</option>
                                    <option value="lima-ica">Lima - Ica</option>
                                    <option value="lima-pisco">Lima - Pisco</option>
                                    <option value="lima-chincha">Lima - Chincha</option>
                                    <option value="nazca-lima">Nazca - Lima</option>
                                    <option value="ica-lima">Ica - Lima</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nuevo Bus (si aplica)</label>
                                <select id="new-bus" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="">Mantener bus actual</option>
                                    <option value="ABC-123">ABC-123 (Volvo 9400)</option>
                                    <option value="DEF-456">DEF-456 (Mercedes O500)</option>
                                    <option value="GHI-789">GHI-789 (Scania K380)</option>
                                    <option value="JKL-012">JKL-012 (Volvo B11R)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Settings -->
                    <div class="bg-blue-50 p-4 rounded-lg mb-4">
                        <h5 class="font-medium text-gray-800 mb-3">Configuración de Notificaciones</h5>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" id="notify-email" checked class="mr-2">
                                <span class="text-sm">Enviar notificación por correo electrónico</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="notify-sms" class="mr-2">
                                <span class="text-sm">Enviar notificación por SMS (solo urgente)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="notify-whatsapp" class="mr-2">
                                <span class="text-sm">Enviar notificación por WhatsApp</span>
                            </label>
                        </div>

                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Correos adicionales (separados por coma)</label>
                            <input type="email" id="additional-emails"
                                placeholder="supervisor@empresa.com, gerencia@empresa.com"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeRouteDetailsModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" onclick="previewChangeRequest()" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                        <i class="fas fa-eye mr-2"></i>Vista Previa
                    </button>
                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">
                        <i class="fas fa-paper-plane mr-2"></i>Enviar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Request Preview Modal -->
    <div id="change-preview-modal" class="modal">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-2xl w-full mx-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-800">Vista Previa - Solicitud de Cambio</h3>
                <button onclick="closeChangePreviewModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div id="preview-content" class="space-y-4">
                <!-- Se llena dinámicamente -->
            </div>

            <div class="flex justify-end space-x-3 pt-6 border-t">
                <button onclick="closeChangePreviewModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Volver a Editar
                </button>
                <button onclick="confirmChangeRequest()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-check mr-2"></i>Confirmar y Enviar
                </button>
            </div>
        </div>
    </div>

    <!-- Changes History Modal -->
    <div id="changes-history-modal" class="modal">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-6xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-800">Registro de Cambios</h3>
                <button onclick="closeChangesHistoryModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Changes History Filters -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fecha Desde</label>
                        <input type="date" id="history-date-from" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fecha Hasta</label>
                        <input type="date" id="history-date-to" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                        <select id="history-status-filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="all">Todos</option>
                            <option value="pending">Pendiente</option>
                            <option value="approved">Aprobado</option>
                            <option value="rejected">Rechazado</option>
                            <option value="implemented">Implementado</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                        <input type="text" id="history-search" placeholder="Conductor, ruta..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="mt-3 flex space-x-2">
                    <button onclick="filterChangesHistory()" class="px-3 py-2 bg-blue-500 text-white text-sm rounded-lg hover:bg-blue-600">
                        <i class="fas fa-filter mr-1"></i>Filtrar
                    </button>
                    <button onclick="exportChangesHistory()" class="px-3 py-2 bg-green-500 text-white text-sm rounded-lg hover:bg-green-600">
                        <i class="fas fa-download mr-1"></i>Exportar
                    </button>
                </div>
            </div>

            <!-- Changes History Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha/Hora</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Solicitante</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Respuesta</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="changes-history-table">
                        <!-- Se llena dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Alert Modal for Operators -->
    <div id="alert-modal" class="modal">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-md w-full mx-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-800">Enviar Alerta</h3>
                <button onclick="closeAlertModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="alert-form">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Alerta</label>
                        <select id="alert-type" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option>Falta de conductor</option>
                            <option>Tardanza de conductor</option>
                            <option>Problema mecánico</option>
                            <option>Cambio de ruta urgente</option>
                            <option>Emergencia médica</option>
                            <option>Incidente de tráfico</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                        <textarea id="alert-description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Describe la situación..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad</label>
                        <select id="alert-priority" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option value="alta">Alta - Atención Inmediata</option>
                            <option value="media">Media - En 15 minutos</option>
                            <option value="baja">Baja - En 1 hora</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 pt-6">
                    <button type="button" onclick="closeAlertModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fas fa-paper-plane mr-2"></i> Enviar Alerta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Sistema de datos simulados
        const AppData = {
            conductores: [
                { id: 1, code: 'C001', name: 'Juan Pérez', dni: '12345678', origin: 'Lima', service: 'estandar', status: 'disponible', efficiency: 89, punctuality: 92, accumulated_days: 3 },
                { id: 2, code: 'C002', name: 'Carlos Gómez', dni: '87654321', origin: 'Ica', service: 'surbus', status: 'vacaciones', efficiency: 93, punctuality: 88, accumulated_days: 0 },
                { id: 3, code: 'C003', name: 'Luis Ramírez', dni: '11223344', origin: 'Pisco', service: 'nazca', status: 'descanso', efficiency: 76, punctuality: 84, accumulated_days: 0 },
                { id: 4, code: 'C004', name: 'Miguel Torres', dni: '55667788', origin: 'Lima', service: 'estandar', status: 'disponible', efficiency: 95, punctuality: 96, accumulated_days: 2 },
                { id: 5, code: 'C005', name: 'Roberto Silva', dni: '99887766', origin: 'Chincha', service: 'surbus', status: 'disponible', efficiency: 87, punctuality: 90, accumulated_days: 6 },
                { id: 6, code: 'C006', name: 'Fernando Cruz', dni: '44556677', origin: 'Nazca', service: 'nazca', status: 'suspendido', efficiency: 72, punctuality: 75, accumulated_days: 0 }
            ],
            buses: [
                { id: 1, plate: 'ABC-123', model: 'Volvo 9400', capacity: 52, mileage: 185000, status: 'operativo', nextMaintenance: '2024-02-15' },
                { id: 2, plate: 'DEF-456', model: 'Mercedes O500', capacity: 48, mileage: 220000, status: 'mantenimiento', nextMaintenance: '2024-01-20' },
                { id: 3, plate: 'GHI-789', model: 'Scania K380', capacity: 50, mileage: 156000, status: 'operativo', nextMaintenance: '2024-03-01' },
                { id: 4, plate: 'JKL-012', model: 'Volvo B11R', capacity: 44, mileage: 245000, status: 'reparacion', nextMaintenance: '2024-01-25' }
            ],
            parameters: [
                { id: 1, key: 'ORIGEN', value: 'Lima,Chincha,Ica,Nazca,Pisco', type: 'array', description: 'Orígenes disponibles del conductor' },
                { id: 2, key: 'REGIMEN', value: '6x1,26x4', type: 'enum', description: 'Tipos de régimen laboral' },
                { id: 3, key: 'SERVICIO', value: 'Estándar,Surbus,Nazca', type: 'array', description: 'Tipos de servicio disponibles' },
                { id: 4, key: 'RATIO_MAX_RUTAS_CORTAS', value: '4', type: 'integer', description: 'Máximo de rutas cortas semanales' },
                { id: 5, key: 'HORAS_DESCANSO_MIN', value: '12', type: 'integer', description: 'Horas mínimas de descanso entre turnos' },
                { id: 6, key: 'DIAS_MAX_SIN_DESCANSO', value: '6', type: 'integer', description: 'Días máximos sin descanso semanal' }
            ],
            planningData: {
                week: '15-21 Enero 2024',
                conductors: [
                    {
                        id: 1,
                        name: 'Juan Pérez (#C001)',
                        schedule: [
                            { // Lunes
                                shifts: [
                                    { type: 'larga', route: 'Lima-Nazca', time: '06:00-14:00', code: 'L1' },
                                    { type: 'corta', route: 'Lima-Chincha', time: '16:00-18:00', code: 'C1' }
                                ]
                            },
                            { // Martes
                                shifts: [{ type: 'descanso', route: 'Descanso', time: '', code: 'D' }]
                            },
                            { // Miércoles
                                shifts: [
                                    { type: 'corta', route: 'Lima-Ica', time: '08:00-10:00', code: 'C1' },
                                    { type: 'corta', route: 'Lima-Pisco', time: '12:00-14:00', code: 'C2' },
                                    { type: 'corta', route: 'Lima-Chincha', time: '16:00-18:00', code: 'C3' }
                                ]
                            },
                            { // Jueves
                                shifts: [
                                    { type: 'larga', route: 'Lima-Nazca', time: '06:00-14:00', code: 'L1' },
                                    { type: 'larga', route: 'Nazca-Lima', time: '16:00-00:00', code: 'L2' }
                                ]
                            },
                            { // Viernes
                                shifts: [{ type: 'descanso', route: 'Descanso', time: '', code: 'D' }]
                            },
                            { // Sábado
                                shifts: [
                                    { type: 'larga', route: 'Lima-Ica', time: '08:00-16:00', code: 'L1' },
                                    { type: 'corta', route: 'Ica-Lima', time: '18:00-20:00', code: 'C1' }
                                ]
                            },
                            { // Domingo
                                shifts: [
                                    { type: 'corta', route: 'Lima-Pisco', time: '10:00-12:00', code: 'C1' },
                                    { type: 'corta', route: 'Lima-Chincha', time: '14:00-16:00', code: 'C2' }
                                ]
                            }
                        ]
                    },
                    {
                        id: 2,
                        name: 'Carlos Gómez (#C002)',
                        schedule: [
                            { shifts: [{ type: 'descanso', route: 'Descanso', time: '', code: 'D' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Pisco', time: '10:00-18:00', code: 'L1' }, { type: 'corta', route: 'Pisco-Lima', time: '20:00-22:00', code: 'C1' }] },
                            { shifts: [{ type: 'descanso', route: 'Descanso', time: '', code: 'D' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Nazca', time: '06:00-14:00', code: 'L1' }] },
                            { shifts: [{ type: 'corta', route: 'Lima-Ica', time: '08:00-10:00', code: 'C1' }, { type: 'corta', route: 'Lima-Chincha', time: '12:00-14:00', code: 'C2' }, { type: 'corta', route: 'Lima-Pisco', time: '16:00-18:00', code: 'C3' }] },
                            { shifts: [{ type: 'descanso', route: 'Descanso', time: '', code: 'D' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Nazca', time: '06:00-14:00', code: 'L1' }, { type: 'corta', route: 'Nazca-Ica', time: '16:00-18:00', code: 'C1' }] }
                        ]
                    },
                    {
                        id: 3,
                        name: 'Luis Ramírez (#C003)',
                        schedule: [
                            { shifts: [{ type: 'larga', route: 'Lima-Ica', time: '08:00-16:00', code: 'L1' }, { type: 'corta', route: 'Ica-Pisco', time: '18:00-20:00', code: 'C1' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Nazca', time: '06:00-14:00', code: 'L1' }] },
                            { shifts: [{ type: 'descanso', route: 'Descanso', time: '', code: 'D' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Pisco', time: '10:00-18:00', code: 'L1' }, { type: 'larga', route: 'Pisco-Lima', time: '20:00-04:00', code: 'L2' }] },
                            { shifts: [{ type: 'descanso', route: 'Descanso', time: '', code: 'D' }] },
                            { shifts: [{ type: 'corta', route: 'Lima-Ica', time: '08:00-10:00', code: 'C1' }, { type: 'corta', route: 'Lima-Chincha', time: '12:00-14:00', code: 'C2' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Nazca', time: '06:00-14:00', code: 'L1' }] }
                        ]
                    },
                    {
                        id: 4,
                        name: 'Miguel Torres (#C004)',
                        schedule: [
                            { shifts: [{ type: 'corta', route: 'Lima-Chincha', time: '09:00-11:00', code: 'C1' }, { type: 'corta', route: 'Lima-Ica', time: '13:00-15:00', code: 'C2' }, { type: 'corta', route: 'Lima-Pisco', time: '17:00-19:00', code: 'C3' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Nazca', time: '06:00-14:00', code: 'L1' }, { type: 'corta', route: 'Nazca-Ica', time: '16:00-18:00', code: 'C1' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Pisco', time: '10:00-18:00', code: 'L1' }] },
                            { shifts: [{ type: 'descanso', route: 'Descanso', time: '', code: 'D' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Ica', time: '08:00-16:00', code: 'L1' }, { type: 'larga', route: 'Ica-Lima', time: '18:00-02:00', code: 'L2' }] },
                            { shifts: [{ type: 'corta', route: 'Lima-Chincha', time: '09:00-11:00', code: 'C1' }, { type: 'corta', route: 'Lima-Pisco', time: '14:00-16:00', code: 'C2' }] },
                            { shifts: [{ type: 'descanso', route: 'Descanso', time: '', code: 'D' }] }
                        ]
                    },
                    {
                        id: 5,
                        name: 'Roberto Silva (#C005)',
                        schedule: [
                            { shifts: [{ type: 'larga', route: 'Lima-Nazca', time: '06:00-14:00', code: 'L1' }] },
                            { shifts: [{ type: 'descanso', route: 'Descanso', time: '', code: 'D' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Ica', time: '08:00-16:00', code: 'L1' }, { type: 'corta', route: 'Ica-Chincha', time: '18:00-20:00', code: 'C1' }] },
                            { shifts: [{ type: 'corta', route: 'Lima-Pisco', time: '10:00-12:00', code: 'C1' }, { type: 'corta', route: 'Lima-Chincha', time: '14:00-16:00', code: 'C2' }, { type: 'corta', route: 'Lima-Ica', time: '18:00-20:00', code: 'C3' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Nazca', time: '06:00-14:00', code: 'L1' }, { type: 'larga', route: 'Nazca-Lima', time: '16:00-00:00', code: 'L2' }] },
                            { shifts: [{ type: 'descanso', route: 'Descanso', time: '', code: 'D' }] },
                            { shifts: [{ type: 'larga', route: 'Lima-Pisco', time: '10:00-18:00', code: 'L1' }] }
                        ]
                    }
                ]
            },
            validations: [
                { id: 1, type: 'DESCANSO_001', severity: 'critica', message: 'Conductor #C005 necesita descanso - 6 días consecutivos', conductor: 'Roberto Silva', time: '5 minutos' },
                { id: 2, type: 'EFICIENCIA_002', severity: 'advertencia', message: 'Conductor #C006 con eficiencia del 72%', conductor: 'Fernando Cruz', time: '15 minutos' },
                { id: 3, type: 'PUNTUALIDAD_003', severity: 'advertencia', message: 'Conductor #C003 con puntualidad del 84%', conductor: 'Luis Ramírez', time: '30 minutos' },
                { id: 4, type: 'TURNO_VACIO_004', severity: 'info', message: '3 turnos sin asignar para mañana', conductor: null, time: '1 hora' }
            ],
            changesHistory: [
                {
                    id: 1,
                    date: '2024-01-20 14:30',
                    requester: 'Operador Central',
                    type: 'Cambio de Conductor',
                    detail: 'Juan Pérez (#C001) - Lima-Nazca 06:00 → Carlos Gómez (#C002)',
                    reason: 'Falta del conductor por enfermedad',
                    status: 'implemented',
                    response: 'Cambio implementado exitosamente',
                    priority: 'alta',
                    emailSent: true,
                    approvedBy: 'Supervisor Operaciones'
                },
                {
                    id: 2,
                    date: '2024-01-20 09:15',
                    requester: 'Operador Turno A',
                    type: 'Cambio de Horario',
                    detail: 'Lima-Ica 08:00 → 10:00 por problema de tráfico',
                    reason: 'Congestión vehicular en Panamericana Sur',
                    status: 'approved',
                    response: 'Aprobado para implementación',
                    priority: 'media',
                    emailSent: true,
                    approvedBy: 'Jefe de Operaciones'
                },
                {
                    id: 3,
                    date: '2024-01-19 22:45',
                    requester: 'Operador Nocturno',
                    type: 'Cambio de Bus',
                    detail: 'Bus ABC-123 → DEF-456 por problema mecánico',
                    reason: 'Falla en sistema de frenos',
                    status: 'rejected',
                    response: 'Bus DEF-456 no disponible. Usar GHI-789',
                    priority: 'critica',
                    emailSent: true,
                    approvedBy: 'Supervisor Mantenimiento'
                },
                {
                    id: 4,
                    date: '2024-01-19 16:20',
                    requester: 'Operador Central',
                    type: 'Cancelación de Turno',
                    detail: 'Lima-Pisco 18:00 - Cancelación por emergencia',
                    reason: 'Emergencia familiar del conductor',
                    status: 'pending',
                    response: 'En evaluación por supervisión',
                    priority: 'media',
                    emailSent: true,
                    approvedBy: null
                }
            ],
            frequencies: {
                '06:00': 8, '07:00': 5, '08:00': 12, '09:00': 7, '10:00': 10,
                '11:00': 3, '12:00': 6, '13:00': 4, '14:00': 8, '15:00': 2,
                '16:00': 9, '17:00': 6, '18:00': 7, '19:00': 3, '20:00': 5
            }
        };

        // Enhanced Planning Module Functions
        function generatePlanningGrid() {
            const container = document.getElementById('planning-grid-enhanced');
            if (!container) return;

            const days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

            // Generate header
            let gridHTML = '<div class="planning-cell planning-header">Conductor</div>';
            days.forEach(day => {
                gridHTML += `<div class="planning-cell planning-header">${day}</div>`;
            });

            // Filter conductors if needed
            const conductorFilter = document.getElementById('conductor-filter')?.value || '';
            let filteredConductors = AppData.planningData.conductors;

            if (conductorFilter) {
                filteredConductors = AppData.planningData.conductors.filter(c =>
                    c.name.includes(conductorFilter)
                );
            }

            let totalTurnos = 0, totalLargas = 0, totalCortas = 0, totalDescansos = 0;

            // Generate conductor rows
            filteredConductors.forEach(conductor => {
                gridHTML += `<div class="planning-cell planning-driver">${conductor.name}</div>`;

                conductor.schedule.forEach((daySchedule, dayIndex) => {
                    gridHTML += '<div class="planning-cell">';

                    daySchedule.shifts.forEach(shift => {
                        totalTurnos++;

                        if (shift.type === 'descanso') {
                            totalDescansos++;
                            gridHTML += `<div class="turno-descanso">${shift.route}</div>`;
                        } else {
                            if (shift.type === 'larga') totalLargas++;
                            if (shift.type === 'corta') totalCortas++;

                            const shiftClass = shift.type === 'larga' ? 'turno-larga' : 'turno-corta';
                            const isClickable = getCurrentUserRole() === 'operator';
                            const clickHandler = isClickable ? `onclick="showRouteDetails('${conductor.id}', '${dayIndex}', '${shift.route}', '${shift.time}', '${shift.type}')"` : '';

                            gridHTML += `
                                <div class="${shiftClass} ${isClickable ? 'cursor-pointer hover:opacity-80' : ''}"
                                     title="${shift.route} - ${shift.time}" ${clickHandler}>
                                    <div style="font-weight: bold;">${shift.code}</div>
                                    <div>${shift.route}</div>
                                    <div style="font-size: 9px;">${shift.time}</div>
                                </div>
                            `;
                        }
                    });

                    gridHTML += '</div>';
                });
            });

            container.innerHTML = gridHTML;
            updatePlanningStats(totalTurnos, totalLargas, totalCortas, totalDescansos);
            generateFrequencyChart();
        }

        function updatePlanningStats(total, largas, cortas, descansos) {
            const statsElement = document.getElementById('planning-stats');
            if (statsElement) {
                statsElement.innerHTML = `
                    Total Turnos: <span class="font-medium">${total}</span> |
                    Rutas Largas: <span class="font-medium">${largas}</span> |
                    Rutas Cortas: <span class="font-medium">${cortas}</span> |
                    Descansos: <span class="font-medium">${descansos}</span>
                `;
            }
        }

        function generateFrequencyChart() {
            const container = document.getElementById('frequency-chart');
            if (!container) return;

            const frequencies = AppData.frequencies;
            container.innerHTML = Object.entries(frequencies).map(([hour, count]) => `
                <div class="text-center p-2 bg-gray-100 rounded">
                    <div class="text-xs font-medium">${hour}</div>
                    <div class="text-lg font-bold text-blue-600">${count}</div>
                    <div class="w-full bg-gray-200 rounded h-2 mt-1">
                        <div class="bg-blue-500 h-2 rounded" style="width: ${(count/12)*100}%"></div>
                    </div>
                </div>
            `).join('');
        }

        function filterPlanningGrid() {
            generatePlanningGrid();
            showNotification('Filtros aplicados a la planificación', 'info');
        }

        function sortPlanningGrid() {
            const sortType = document.getElementById('tramo-sort')?.value || 'horario';
            // En una implementación real, aquí se ordenaría la data
            showNotification(`Planificación ordenada por: ${sortType}`, 'info');
            generatePlanningGrid();
        }

        function toggleFrequencyView() {
            const viewType = document.getElementById('frequency-view')?.value || 'normal';
            const timelineView = document.getElementById('timeline-view');

            if (viewType === 'timeline') {
                timelineView?.classList.remove('hidden');
                generateTimelineView();
            } else {
                timelineView?.classList.add('hidden');
            }

            showNotification(`Vista cambiada a: ${viewType}`, 'info');
        }

        function generateTimelineView() {
            const container = document.getElementById('timeline-container');
            if (!container) return;

            const timeSlots = ['06:00', '08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'];

            container.innerHTML = timeSlots.map(time => `
                <div class="bg-white border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h5 class="font-medium text-gray-800">Tramo ${time}</h5>
                        <span class="text-sm text-gray-500">${AppData.frequencies[time] || 0} turnos</span>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-xs">
                        <div class="p-2 bg-blue-50 rounded">Lima-Nazca</div>
                        <div class="p-2 bg-green-50 rounded">Lima-Ica</div>
                        <div class="p-2 bg-yellow-50 rounded">Lima-Pisco</div>
                        <div class="p-2 bg-purple-50 rounded">Lima-Chincha</div>
                    </div>
                </div>
            `).join('');
        }

        function resetFilters() {
            document.getElementById('conductor-filter').value = '';
            document.getElementById('tramo-sort').value = 'horario';
            document.getElementById('frequency-view').value = 'normal';
            generatePlanningGrid();
            showNotification('Filtros restablecidos', 'info');
        }

        function toggleDetailView() {
            // Toggle between normal and detailed view
            showNotification('Vista detallada activada', 'info');
        }

        // Enhanced Operator Dashboard Functions
        function loadOperatorDashboard() {
            generateOperatorPlanningGrid();
            loadRealtimeMonitor();
            loadOperatorAlerts();
            updateOperatorStats();
        }

        function generateOperatorPlanningGrid() {
            const container = document.getElementById('operator-planning-grid');
            if (!container) return;

            const days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

            // Enhanced grid for operators with better organization
            let gridHTML = `
                <div class="grid grid-cols-8 gap-1 bg-gray-200 rounded-lg overflow-hidden">
                    <div class="bg-gray-600 text-white p-3 font-bold text-center">Conductor</div>
                    ${days.map(day => `<div class="bg-gray-600 text-white p-3 font-bold text-center text-sm">${day}</div>`).join('')}
                `;

            AppData.planningData.conductors.forEach(conductor => {
                gridHTML += `<div class="bg-gray-100 p-3 font-medium text-sm">${conductor.name}</div>`;

                conductor.schedule.forEach((daySchedule, dayIndex) => {
                    gridHTML += '<div class="bg-white p-2 min-h-[80px]">';

                    daySchedule.shifts.forEach((shift, shiftIndex) => {
                        if (shift.type === 'descanso') {
                            gridHTML += `<div class="turno-descanso text-center py-1 mb-1">${shift.route}</div>`;
                        } else {
                            const shiftClass = shift.type === 'larga' ? 'turno-larga' : 'turno-corta';
                            gridHTML += `
                                <div class="${shiftClass} cursor-pointer hover:opacity-80 transition-opacity p-1 mb-1"
                                     onclick="showRouteDetails('${conductor.id}', '${dayIndex}', '${shift.route}', '${shift.time}', '${shift.type}', '${conductor.name}', ${shiftIndex})"
                                     title="Click para ver detalles y solicitar cambios">
                                    <div class="font-bold text-xs">${shift.code}</div>
                                    <div class="text-xs">${shift.route}</div>
                                    <div class="text-xs opacity-75">${shift.time}</div>
                                </div>
                            `;
                        }
                    });

                    gridHTML += '</div>';
                });
            });

            gridHTML += '</div>';
            container.innerHTML = gridHTML;
        }

        function loadRealtimeMonitor() {
            const container = document.getElementById('realtime-monitor');
            if (!container) return;

            const realTimeData = [
                { route: 'Lima-Nazca', conductor: 'Juan Pérez', status: 'En Ruta', progress: 65, eta: '14:30' },
                { route: 'Lima-Ica', conductor: 'Miguel Torres', status: 'Próximo Embarque', progress: 0, eta: '15:00' },
                { route: 'Lima-Pisco', conductor: 'Roberto Silva', status: 'Retrasado', progress: 30, eta: '16:15' },
                { route: 'Lima-Chincha', conductor: 'Carlos Gómez', status: 'En Terminal', progress: 95, eta: '14:45' }
            ];

            container.innerHTML = realTimeData.map(data => {
                const statusColors = {
                    'En Ruta': 'green',
                    'Próximo Embarque': 'blue',
                    'Retrasado': 'red',
                    'En Terminal': 'purple'
                };
                const color = statusColors[data.status];

                return `
                    <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <h5 class="font-medium text-gray-900">${data.route}</h5>
                            <span class="px-2 py-1 bg-${color}-100 text-${color}-800 rounded-full text-xs font-medium">${data.status}</span>
                        </div>
                        <p class="text-sm text-gray-600 mb-2">${data.conductor}</p>
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                            <span>Progreso: ${data.progress}%</span>
                            <span>ETA: ${data.eta}</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill bg-${color}-500" style="width: ${data.progress}%"></div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function loadOperatorAlerts() {
            const container = document.getElementById('operator-alerts');
            if (!container) return;

            const alerts = [
                { type: 'Tardanza', message: 'Conductor #C005 - 30 min retraso en Lima-Pisco', time: '15 min', severity: 'warning' },
                { type: 'Cambio Aprobado', message: 'Solicitud #1001 aprobada - Nuevo conductor asignado', time: '1 hora', severity: 'success' },
                { type: 'Bus en Mantenimiento', message: 'Bus ABC-123 en mantenimiento preventivo', time: '2 horas', severity: 'info' }
            ];

            container.innerHTML = alerts.map(alert => {
                const severityIcons = {
                    'warning': 'exclamation-triangle',
                    'success': 'check-circle',
                    'info': 'info-circle',
                    'error': 'times-circle'
                };

                const severityColors = {
                    'warning': 'yellow',
                    'success': 'green',
                    'info': 'blue',
                    'error': 'red'
                };

                const color = severityColors[alert.severity];
                const icon = severityIcons[alert.severity];

                return `
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-${icon} text-${color}-500"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <h5 class="font-medium text-gray-900">${alert.type}</h5>
                                <p class="text-sm text-gray-600 mt-1">${alert.message}</p>
                                <span class="text-xs text-gray-400">Hace ${alert.time}</span>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function updateOperatorStats() {
            document.getElementById('op-stats-active').textContent = '24';
            document.getElementById('op-stats-scheduled').textContent = '18';
            document.getElementById('op-stats-pending').textContent = '3';
            document.getElementById('op-stats-issues').textContent = '2';
        }

        // Route Details and Change Request Functions
        function showRouteDetails(conductorId, dayIndex, route, time, type, conductorName, shiftIndex) {
            const conductor = AppData.conductores.find(c => c.id == conductorId);
            if (!conductor) return;

            // Fill modal with current information
            document.getElementById('modal-conductor').textContent = conductorName || conductor.name;
            document.getElementById('modal-date').textContent = getCurrentDateForDay(dayIndex);
            document.getElementById('modal-route').textContent = route;
            document.getElementById('modal-time').textContent = time;
            document.getElementById('modal-type').textContent = type === 'larga' ? 'Ruta Larga' : 'Ruta Corta';
            document.getElementById('modal-bus').textContent = getAssignedBus();
            document.getElementById('modal-status').textContent = 'Programado';

            // Fill conductor statistics
            document.getElementById('modal-efficiency').textContent = conductor.efficiency + '%';
            document.getElementById('modal-punctuality').textContent = conductor.punctuality + '%';
            document.getElementById('modal-days-worked').textContent = conductor.accumulated_days + ' días';
            document.getElementById('modal-last-rest').textContent = '2024-01-18';
            document.getElementById('modal-short-routes').textContent = conductor.weekly_short_routes + '/4';

            // Show modal
            document.getElementById('route-details-modal').classList.add('active');
        }

        function getCurrentDateForDay(dayIndex) {
            const days = ['2024-01-15', '2024-01-16', '2024-01-17', '2024-01-18', '2024-01-19', '2024-01-20', '2024-01-21'];
            return days[dayIndex] || '2024-01-15';
        }

        function getAssignedBus() {
            const buses = ['ABC-123', 'DEF-456', 'GHI-789', 'JKL-012'];
            return buses[Math.floor(Math.random() * buses.length)];
        }

        function closeRouteDetailsModal() {
            document.getElementById('route-details-modal').classList.remove('active');
        }

        function previewChangeRequest() {
            const changeType = document.getElementById('change-type').value;
            const priority = document.getElementById('change-priority').value;
            const reason = document.getElementById('change-reason').value;
            const description = document.getElementById('change-description').value;

            if (!changeType || !priority || !reason || !description) {
                showNotification('Por favor complete todos los campos requeridos', 'error');
                return;
            }

            // Generate preview content
            const previewHTML = `
                <div class="space-y-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h4 class="font-medium text-blue-800 mb-2">Resumen de la Solicitud</h4>
                        <div class="text-sm space-y-1">
                            <div><strong>Tipo:</strong> ${changeType}</div>
                            <div><strong>Prioridad:</strong> ${priority.toUpperCase()}</div>
                            <div><strong>Motivo:</strong> ${reason}</div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-800 mb-2">Descripción</h4>
                        <p class="text-sm text-gray-600">${description}</p>
                    </div>

                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <h4 class="font-medium text-yellow-800 mb-2">Notificaciones</h4>
                        <div class="text-sm space-y-1">
                            <div>✓ Se enviará por correo electrónico</div>
                            <div>✓ Notificación al supervisor de turno</div>
                            <div>✓ Registro en el sistema de cambios</div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('preview-content').innerHTML = previewHTML;
            document.getElementById('change-preview-modal').classList.add('active');
        }

        function closeChangePreviewModal() {
            document.getElementById('change-preview-modal').classList.remove('active');
        }

        function confirmChangeRequest() {
            const changeData = {
                id: AppData.changesHistory.length + 1,
                date: new Date().toLocaleString('es-PE'),
                requester: 'Operador Central',
                type: document.getElementById('change-type').value,
                detail: `${document.getElementById('modal-conductor').textContent} - ${document.getElementById('modal-route').textContent}`,
                reason: document.getElementById('change-description').value,
                status: 'pending',
                response: 'Solicitud recibida, en proceso de evaluación',
                priority: document.getElementById('change-priority').value,
                emailSent: true,
                approvedBy: null
            };

            AppData.changesHistory.unshift(changeData);

            // Simulate email sending
            simulateEmailSending(changeData);

            closeChangePreviewModal();
            closeRouteDetailsModal();

            showNotification('Solicitud de cambio enviada exitosamente. Se ha notificado por correo electrónico.', 'success');
        }

        function simulateEmailSending(changeData) {
            // Simulate email sending process
            setTimeout(() => {
                showNotification(`📧 Correo enviado a: supervisor@empresa.com`, 'info');
            }, 1000);

            setTimeout(() => {
                showNotification(`📧 Correo enviado a: operaciones@empresa.com`, 'info');
            }, 2000);

            // Create email content simulation
            const emailContent = `
                SOLICITUD DE CAMBIO - SIPAT
                ================================

                Fecha: ${changeData.date}
                Solicitante: ${changeData.requester}
                Prioridad: ${changeData.priority.toUpperCase()}

                Tipo de Cambio: ${changeData.type}
                Detalle: ${changeData.detail}
                Motivo: ${changeData.reason}

                Esta solicitud requiere aprobación.
                Acceda al sistema SIPAT para revisar y aprobar.

                ================================
                Sistema SIPAT - Transporte
            `;

            console.log('Email simulado enviado:', emailContent);
        }

        // Changes History Functions
        function showChangesHistory() {
            loadChangesHistoryTable();
            document.getElementById('changes-history-modal').classList.add('active');
        }

        function closeChangesHistoryModal() {
            document.getElementById('changes-history-modal').classList.remove('active');
        }

        function loadChangesHistoryTable() {
            const container = document.getElementById('changes-history-table');
            if (!container) return;

            container.innerHTML = AppData.changesHistory.map(change => {
                const statusColors = {
                    'pending': { bg: 'yellow', text: 'yellow' },
                    'approved': { bg: 'green', text: 'green' },
                    'rejected': { bg: 'red', text: 'red' },
                    'implemented': { bg: 'blue', text: 'blue' }
                };

                const statusLabels = {
                    'pending': 'Pendiente',
                    'approved': 'Aprobado',
                    'rejected': 'Rechazado',
                    'implemented': 'Implementado'
                };

                const priorityColors = {
                    'baja': 'gray',
                    'media': 'yellow',
                    'alta': 'orange',
                    'critica': 'red'
                };

                const color = statusColors[change.status];
                const priorityColor = priorityColors[change.priority];

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${change.date}
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-${change.emailSent ? 'check' : 'times'} mr-1"></i>
                                ${change.emailSent ? 'Email enviado' : 'Sin notificar'}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${change.requester}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 bg-${priorityColor}-100 text-${priorityColor}-800 rounded-full text-xs font-medium">
                                ${change.type}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate" title="${change.detail}">
                            ${change.detail}
                            <div class="text-xs text-gray-500 mt-1">${change.reason}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 bg-${color.bg}-100 text-${color.text}-800 rounded-full text-xs font-medium">
                                ${statusLabels[change.status]}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="${change.response}">
                            ${change.response}
                            ${change.approvedBy ? `<div class="text-xs text-gray-400 mt-1">Por: ${change.approvedBy}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="viewChangeDetails(${change.id})" class="text-blue-600 hover:text-blue-900 mr-3">Ver</button>
                            ${change.status === 'pending' ? `
                                <button onclick="approveChange(${change.id})" class="text-green-600 hover:text-green-900 mr-3">Aprobar</button>
                                <button onclick="rejectChange(${change.id})" class="text-red-600 hover:text-red-900">Rechazar</button>
                            ` : ''}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function filterChangesHistory() {
            // In a real implementation, this would filter the data
            loadChangesHistoryTable();
            showNotification('Filtros aplicados al historial', 'info');
        }

        function exportChangesHistory() {
            // Simulate export
            showNotification('Historial de cambios exportado a Excel', 'success');
        }

        function viewChangeDetails(changeId) {
            const change = AppData.changesHistory.find(c => c.id === changeId);
            if (change) {
                alert(`Detalles del cambio #${changeId}:\n\n${JSON.stringify(change, null, 2)}`);
            }
        }

        function approveChange(changeId) {
            const change = AppData.changesHistory.find(c => c.id === changeId);
            if (change) {
                change.status = 'approved';
                change.response = 'Cambio aprobado por administrador';
                change.approvedBy = 'Admin User';
                loadChangesHistoryTable();
                showNotification(`Cambio #${changeId} aprobado exitosamente`, 'success');
            }
        }

        function rejectChange(changeId) {
            const change = AppData.changesHistory.find(c => c.id === changeId);
            if (change) {
                const reason = prompt('Motivo del rechazo:');
                if (reason) {
                    change.status = 'rejected';
                    change.response = `Rechazado: ${reason}`;
                    change.approvedBy = 'Admin User';
                    loadChangesHistoryTable();
                    showNotification(`Cambio #${changeId} rechazado`, 'info');
                }
            }
        }

        // Operator specific functions
        function refreshOperatorView() {
            generateOperatorPlanningGrid();
            loadRealtimeMonitor();
            loadOperatorAlerts();
            showNotification('Vista actualizada', 'success');
        }

        function filterOperatorView() {
            generateOperatorPlanningGrid();
            showNotification('Filtros aplicados', 'info');
        }

        function searchOperatorView() {
            const query = document.getElementById('operator-search')?.value || '';
            if (query.length > 2) {
                generateOperatorPlanningGrid();
                showNotification(`Buscando: "${query}"`, 'info');
            }
        }

        function getCurrentUserRole() {
            // Get current role from UI
            const roleButton = document.getElementById('role-switch-btn');
            const roleText = roleButton?.querySelector('span')?.textContent || 'Administrador';

            const roleMap = {
                'Administrador': 'admin',
                'Planificador': 'planner',
                'Programador': 'programmer',
                'Operador': 'operator'
            };

            return roleMap[roleText] || 'admin';
        }

        // Sistema de tiempo real
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-PE', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = now.toLocaleDateString('es-PE', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            const timeElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            if (timeElement) timeElement.textContent = timeString;
            if (dateElement) dateElement.textContent = dateString;
        }

        setInterval(updateTime, 1000);
        updateTime();

        // Sistema de navegación
        function initializeNavigation() {
            // Sidebar toggle
            document.getElementById('sidebar-toggle').addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                const contentArea = document.getElementById('content-area');
                const icon = this.querySelector('i');

                sidebar.classList.toggle('sidebar-collapsed');
                contentArea.classList.toggle('content-expanded');

                if (sidebar.classList.contains('sidebar-collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            });

            // Tab navigation
            document.addEventListener('click', function(e) {
                if (e.target.matches('[data-tab]') || e.target.closest('[data-tab]')) {
                    e.preventDefault();
                    const tabButton = e.target.matches('[data-tab]') ? e.target : e.target.closest('[data-tab]');
                    const tabId = tabButton.getAttribute('data-tab');

                    // Hide all tab contents
                    document.querySelectorAll('.tab-content').forEach(tab => {
                        tab.classList.remove('active');
                    });

                    // Show selected tab
                    const selectedTab = document.getElementById(tabId);
                    if (selectedTab) {
                        selectedTab.classList.add('active');

                        // Update page title and subtitle
                        updatePageTitle(tabId);

                        // Load content for the tab
                        loadTabContent(tabId);
                    }

                    // Remove active class from all menu items
                    document.querySelectorAll('.menu-item').forEach(item => {
                        item.classList.remove('bg-blue-700');
                    });

                    // Add active class to clicked menu item
                    tabButton.classList.add('bg-blue-700');
                }
            });

            // Role switching
            document.getElementById('role-switch-btn').addEventListener('click', function() {
                document.getElementById('role-switch-dropdown').classList.toggle('hidden');
            });

            document.addEventListener('click', function(e) {
                if (e.target.matches('[data-role]')) {
                    e.preventDefault();
                    const role = e.target.getAttribute('data-role');
                    switchRole(role);
                    document.getElementById('role-switch-dropdown').classList.add('hidden');
                }
            });

            // Notifications dropdown
            document.getElementById('notifications-btn').addEventListener('click', function() {
                document.getElementById('notifications-dropdown').classList.toggle('hidden');
                loadNotifications();
            });

            // User menu dropdown
            document.getElementById('user-menu-btn').addEventListener('click', function() {
                document.getElementById('user-menu-dropdown').classList.toggle('hidden');
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                closeDropdowns(e);
            });
        }

        function updatePageTitle(tabId) {
            const titles = {
                'dashboard-admin': { title: 'Dashboard Administrador', subtitle: 'Panel completo de control y gestión del sistema' },
                'dashboard-planner': { title: 'Dashboard Planificador', subtitle: 'Panel de control para planificación de rutas y turnos' },
                'dashboard-programmer': { title: 'Dashboard Programador', subtitle: 'Control operativo de conductores y asignaciones' },
                'dashboard-operator': { title: 'Dashboard Operador', subtitle: 'Monitoreo y control operativo en tiempo real' },
                'parametros': { title: 'Módulo de Parámetros', subtitle: 'Configuración de parámetros del sistema y algoritmos' },
                'logica': { title: 'Módulo de Lógica', subtitle: 'Configuración de reglas y algoritmos de asignación' },
                'registros': { title: 'Módulo de Registros', subtitle: 'Historial y auditoría del sistema' },
                'conductores': { title: 'Módulo de Conductores', subtitle: 'Gestión integral de conductores y sus asignaciones' },
                'buses': { title: 'Módulo de Buses', subtitle: 'Gestión de flota de buses y mantenimiento' },
                'planificacion': { title: 'Módulo de Planificación', subtitle: 'Gestión inteligente de asignación de turnos múltiples' }
            };

            const titleData = titles[tabId] || { title: 'Dashboard', subtitle: 'Panel de control' };
            document.getElementById('page-title').textContent = titleData.title;
            document.getElementById('page-subtitle').textContent = titleData.subtitle;
        }

        function switchRole(role) {
            // Hide all menus
            document.querySelectorAll('.menu-section').forEach(menu => {
                menu.classList.add('hidden');
            });

            // Show selected role menu
            const menuMap = {
                'admin': 'admin-menu',
                'planner': 'planner-menu',
                'programmer': 'programmer-menu',
                'operator': 'operator-menu'
            };

            const selectedMenu = document.getElementById(menuMap[role]);
            if (selectedMenu) {
                selectedMenu.classList.remove('hidden');
            }

            // Update role display
            const roleNames = {
                'admin': 'Administrador',
                'planner': 'Planificador',
                'programmer': 'Programador',
                'operator': 'Operador'
            };

            document.getElementById('role-switch-btn').querySelector('span').textContent = roleNames[role];
            document.getElementById('userrole').textContent = roleNames[role];

            // Switch to appropriate dashboard
            const dashboardMap = {
                'admin': 'dashboard-admin',
                'planner': 'dashboard-planner',
                'programmer': 'dashboard-programmer',
                'operator': 'dashboard-operator'
            };

            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show role dashboard
            const dashboard = document.getElementById(dashboardMap[role]);
            if (dashboard) {
                dashboard.classList.add('active');
                updatePageTitle(dashboardMap[role]);
                loadTabContent(dashboardMap[role]);
            }

            // Update menu active states
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('bg-blue-700');
            });

            // Set first menu item as active
            const firstMenuItem = selectedMenu?.querySelector('.menu-item');
            if (firstMenuItem) {
                firstMenuItem.classList.add('bg-blue-700');
            }
        }

        function closeDropdowns(e) {
            const dropdowns = [
                { btn: 'notifications-btn', dropdown: 'notifications-dropdown' },
                { btn: 'user-menu-btn', dropdown: 'user-menu-dropdown' },
                { btn: 'role-switch-btn', dropdown: 'role-switch-dropdown' }
            ];

            dropdowns.forEach(({ btn, dropdown }) => {
                const button = document.getElementById(btn);
                const dropdownEl = document.getElementById(dropdown);
                if (button && dropdownEl && !button.contains(e.target) && !dropdownEl.contains(e.target)) {
                    dropdownEl.classList.add('hidden');
                }
            });
        }

        // Sistema de carga de contenido
        function loadTabContent(tabId) {
            switch(tabId) {
                case 'dashboard-admin':
                    loadAdminDashboard();
                    break;
                case 'dashboard-planner':
                    loadPlannerDashboard();
                    break;
                case 'dashboard-programmer':
                    loadProgrammerDashboard();
                    break;
                case 'dashboard-operator':
                    loadOperatorDashboard();
                    break;
                case 'planificacion':
                    loadPlanningModule();
                    break;
                case 'parametros':
                    loadParametersModule();
                    break;
                case 'conductores':
                    loadDriversModule();
                    break;
                case 'buses':
                    loadBusesModule();
                    break;
            }
        }

        function loadAdminDashboard() {
            // Update metrics
            updateMetrics();

            // Load recent activities
            loadRecentActivities();

            // Load critical validations
            loadCriticalValidations();

            // Load KPIs
            loadKPIs();
        }

        function updateMetrics() {
            const availableDrivers = AppData.conductores.filter(c => c.status === 'disponible').length;
            const pendingValidations = AppData.validations.filter(v => v.severity === 'critica').length;
            const avgEfficiency = Math.round(AppData.conductores.reduce((sum, c) => sum + c.efficiency, 0) / AppData.conductores.length);

            const metricElements = {
                'metric-conductores': availableDrivers,
                'metric-validaciones': pendingValidations,
                'metric-rutas': 56,
                'metric-eficiencia': avgEfficiency + '%'
            };

            Object.entries(metricElements).forEach(([id, value]) => {
                const element = document.getElementById(id);
                if (element) {
                    animateCounter(element, value);
                }
            });
        }

        function animateCounter(element, targetValue) {
            const isPercentage = typeof targetValue === 'string' && targetValue.includes('%');
            const numericValue = isPercentage ? parseInt(targetValue) : targetValue;
            const currentValue = parseInt(element.textContent) || 0;

            const increment = (numericValue - currentValue) / 20;
            let current = currentValue;

            const animation = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= numericValue) || (increment < 0 && current <= numericValue)) {
                    current = numericValue;
                    clearInterval(animation);
                }
                element.textContent = Math.round(current) + (isPercentage ? '%' : '');
            }, 50);
        }

        function loadRecentActivities() {
            const container = document.getElementById('recent-activities');
            if (!container) return;

            container.innerHTML = AppData.activities.map(activity => `
                <div class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors duration-200">
                    <div class="w-10 h-10 bg-${activity.color}-100 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-${activity.icon} text-${activity.color}-600"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-900">${activity.description}</p>
                        <p class="text-sm text-gray-600">Hace ${activity.time}</p>
                    </div>
                    <span class="px-2 py-1 bg-${activity.color}-100 text-${activity.color}-800 rounded-full text-xs font-medium">
                        ${activity.type.replace('_', ' ').toUpperCase()}
                    </span>
                </div>
            `).join('');
        }

        function loadCriticalValidations() {
            const container = document.getElementById('critical-validations');
            if (!container) return;

            const criticalValidations = AppData.validations.filter(v => v.severity === 'critica' || v.severity === 'advertencia');

            container.innerHTML = criticalValidations.map(validation => {
                const severityColors = {
                    'critica': 'red',
                    'advertencia': 'yellow',
                    'info': 'blue'
                };
                const color = severityColors[validation.severity];

                return `
                    <div class="validation-item validation-${validation.severity} p-4 rounded-lg bg-${color}-50">
                        <div class="flex items-start">
                            <div class="w-8 h-8 bg-${color}-100 rounded-full flex items-center justify-center mr-3 mt-1">
                                <i class="fas fa-${validation.severity === 'critica' ? 'exclamation' : 'exclamation-triangle'} text-${color}-600 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">${validation.type.replace('_', ' ')}</p>
                                <p class="text-sm text-gray-600 mb-2">${validation.message}</p>
                                <div class="flex space-x-2">
                                    <button onclick="resolveValidation(${validation.id})" class="text-xs bg-${color}-600 text-white px-3 py-1 rounded-full hover:bg-${color}-700 transition-colors duration-200">
                                        ${validation.severity === 'critica' ? 'Resolver' : 'Analizar'}
                                    </button>
                                    <button onclick="postponeValidation(${validation.id})" class="text-xs bg-gray-300 text-gray-700 px-3 py-1 rounded-full hover:bg-gray-400 transition-colors duration-200">
                                        ${validation.severity === 'critica' ? 'Posponer' : 'Ignorar'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            // Update critical count
            const criticalCount = document.getElementById('critical-count');
            if (criticalCount) {
                criticalCount.textContent = `${criticalValidations.length} Pendientes`;
            }
        }

        function loadKPIs() {
            const container = document.getElementById('kpis-grid');
            if (!container) return;

            const kpis = [
                { name: 'Eficiencia Promedio', value: 87, icon: 'chart-line', color: 'green', change: '+2% vs. semana anterior' },
                { name: 'Puntualidad', value: 92, icon: 'clock', color: 'blue', change: '+1% vs. semana anterior' },
                { name: 'Cobertura Turnos', value: 98, icon: 'users', color: 'purple', change: 'Estable' },
                { name: 'Satisfacción', value: 89, icon: 'star', color: 'yellow', change: '+3% vs. semana anterior' }
            ];

            container.innerHTML = kpis.map(kpi => `
                <div class="p-6 border border-gray-200 rounded-xl hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm font-medium text-gray-600">${kpi.name}</p>
                        <i class="fas fa-${kpi.icon} text-${kpi.color}-500"></i>
                    </div>
                    <h4 class="text-3xl font-bold text-gray-800 mb-2">${kpi.value}%</h4>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="bg-gradient-to-r from-${kpi.color}-400 to-${kpi.color}-600 h-2 rounded-full" style="width: ${kpi.value}%"></div>
                    </div>
                    <p class="text-xs text-${kpi.color}-600 font-medium">${kpi.change}</p>
                </div>
            `).join('');
        }

        function loadNotifications() {
            const container = document.getElementById('notifications-list');
            if (!container) return;

            container.innerHTML = AppData.validations.map(validation => {
                const severityIcons = {
                    'critica': 'exclamation-circle',
                    'advertencia': 'exclamation-triangle',
                    'info': 'info-circle'
                };
                const severityColors = {
                    'critica': 'red',
                    'advertencia': 'yellow',
                    'info': 'blue'
                };

                return `
                    <div class="validation-item validation-${validation.severity} px-4 py-3 hover:bg-gray-50 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-${severityIcons[validation.severity]} text-${severityColors[validation.severity]}-500 text-lg"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="font-medium text-gray-900">${validation.type.replace('_', ' ')}</p>
                                <p class="text-sm text-gray-600">${validation.message}</p>
                                <p class="text-xs text-gray-400 mt-1">Hace ${validation.time}</p>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Planning Module Functions
        function loadPlanningModule() {
            generatePlanningGrid();
            loadPlanningMetrics();
            loadAlgorithmStatus();
        }

        function generatePlanningGrid() {
            const container = document.getElementById('planning-grid');
            if (!container) return;

            const days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

            // Generate header
            let gridHTML = '<div class="planning-cell planning-header">Conductor</div>';
            days.forEach(day => {
                gridHTML += `<div class="planning-cell planning-header">${day}</div>`;
            });

            // Generate conductor rows
            AppData.planningData.conductors.forEach(conductor => {
                gridHTML += `<div class="planning-cell planning-driver">${conductor.name}</div>`;

                conductor.schedule.forEach(daySchedule => {
                    gridHTML += '<div class="planning-cell">';

                    daySchedule.shifts.forEach(shift => {
                        const shiftClass = shift.type === 'descanso' ? 'turno-descanso' :
                                         shift.type === 'larga' ? 'turno-larga' : 'turno-corta';

                        if (shift.type === 'descanso') {
                            gridHTML += `<div class="${shiftClass}">${shift.route}</div>`;
                        } else {
                            gridHTML += `
                                <div class="${shiftClass}" title="${shift.route} - ${shift.time}">
                                    <div style="font-weight: bold;">${shift.code}</div>
                                    <div>${shift.route}</div>
                                    <div style="font-size: 9px;">${shift.time}</div>
                                </div>
                            `;
                        }
                    });

                    gridHTML += '</div>';
                });
            });

            container.innerHTML = gridHTML;
        }

        function loadPlanningMetrics() {
            const container = document.getElementById('planning-metrics');
            if (!container) return;

            const metrics = [
                { name: 'Cobertura de Turnos', value: 98, color: 'green' },
                { name: 'Distribución Equitativa', value: 94, color: 'blue' },
                { name: 'Cumplimiento Descansos', value: 100, color: 'purple' },
                { name: 'Optimización Rutas', value: 91, color: 'yellow' }
            ];

            container.innerHTML = metrics.map(metric => `
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-600">${metric.name}</span>
                        <span class="text-sm font-bold text-gray-900">${metric.value}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-${metric.color}-600 h-2 rounded-full transition-all duration-500" style="width: ${metric.value}%"></div>
                    </div>
                </div>
            `).join('');
        }

        function loadAlgorithmStatus() {
            const container = document.getElementById('algorithm-details');
            if (!container) return;

            const statusItems = [
                { name: 'Validación de Parámetros', status: 'success', message: 'Todos los parámetros son válidos' },
                { name: 'Análisis de Disponibilidad', status: 'success', message: 'Conductores analizados correctamente' },
                { name: 'Asignación de Turnos', status: 'success', message: 'Turnos asignados con éxito' },
                { name: 'Optimización Final', status: 'success', message: 'Optimización completada' }
            ];

            container.innerHTML = statusItems.map(item => {
                const statusClass = item.status === 'success' ? 'status-success' :
                                  item.status === 'processing' ? 'status-processing' : 'status-pending';
                const iconClass = item.status === 'success' ? 'check-circle' :
                                item.status === 'processing' ? 'sync-alt' : 'clock';
                const iconColor = item.status === 'success' ? 'green' :
                                item.status === 'processing' ? 'blue' : 'gray';

                return `
                    <div class="algorithm-status ${statusClass}">
                        <div class="flex items-center">
                            <i class="fas fa-${iconClass} text-${iconColor}-600 mr-3"></i>
                            <div>
                                <p class="font-medium text-gray-900">${item.name}</p>
                                <p class="text-sm text-gray-600">${item.message}</p>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Parameter Module Functions
        function loadParametersModule() {
            loadParametersTable();
        }

        function loadParametersTable() {
            const container = document.getElementById('parameters-table');
            if (!container) return;

            container.innerHTML = AppData.parameters.map(param => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${param.key}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${param.value}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">${param.type}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">${param.description}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <button onclick="editParameter(${param.id})" class="text-blue-600 hover:text-blue-900 mr-3">Editar</button>
                        <button onclick="deleteParameter(${param.id})" class="text-red-600 hover:text-red-900">Eliminar</button>
                    </td>
                </tr>
            `).join('');
        }

        // Driver Module Functions
        function loadDriversModule() {
            updateDriverStats();
            loadDriversTable();
        }

        function updateDriverStats() {
            const available = AppData.conductores.filter(c => c.status === 'disponible').length;
            const rest = AppData.conductores.filter(c => c.status === 'descanso').length;
            const vacation = AppData.conductores.filter(c => c.status === 'vacaciones').length;
            const critical = AppData.conductores.filter(c => c.efficiency < 80 || c.accumulated_days >= 6).length;

            const stats = {
                'drivers-available': available,
                'drivers-rest': rest,
                'drivers-vacation': vacation,
                'drivers-critical': critical
            };

            Object.entries(stats).forEach(([id, value]) => {
                const element = document.getElementById(id);
                if (element) {
                    animateCounter(element, value);
                }
            });
        }

        function loadDriversTable() {
            const container = document.getElementById('drivers-table');
            if (!container) return;

            container.innerHTML = AppData.conductores.map(driver => {
                const statusClasses = {
                    'disponible': 'status-disponible',
                    'descanso': 'status-descanso',
                    'vacaciones': 'status-vacaciones',
                    'suspendido': 'status-suspendido'
                };

                const efficiencyColor = driver.efficiency >= 90 ? 'green' :
                                      driver.efficiency >= 80 ? 'yellow' : 'red';

                const initials = driver.name.split(' ').map(n => n[0]).join('');

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${driver.code}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-blue-600 font-medium text-sm">${initials}</span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">${driver.name}</div>
                                    <div class="text-sm text-gray-500">DNI: ${driver.dni}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">${driver.origin}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">${driver.service}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="status-badge ${statusClasses[driver.status]}">${driver.status}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-${efficiencyColor}-600 h-2 rounded-full" style="width: ${driver.efficiency}%"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-700">${driver.efficiency}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="editDriver(${driver.id})" class="text-blue-600 hover:text-blue-900 mr-3">Editar</button>
                            <button onclick="assignDriver(${driver.id})" class="text-green-600 hover:text-green-900 mr-3">Asignar</button>
                            <button onclick="suspendDriver(${driver.id})" class="text-red-600 hover:text-red-900">Suspender</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Bus Module Functions
        function loadBusesModule() {
            updateBusStats();
            loadBusesTable();
        }

        function updateBusStats() {
            const operational = AppData.buses.filter(b => b.status === 'operativo').length;
            const maintenance = AppData.buses.filter(b => b.status === 'mantenimiento').length;
            const repair = AppData.buses.filter(b => b.status === 'reparacion').length;
            const availability = Math.round((operational / AppData.buses.length) * 100);

            const stats = {
                'buses-operational': operational,
                'buses-maintenance': maintenance,
                'buses-repair': repair,
                'buses-availability': availability + '%'
            };

            Object.entries(stats).forEach(([id, value]) => {
                const element = document.getElementById(id);
                if (element) {
                    animateCounter(element, value);
                }
            });
        }

        function loadBusesTable() {
            const container = document.getElementById('buses-table');
            if (!container) return;

            container.innerHTML = AppData.buses.map(bus => {
                const statusClasses = {
                    'operativo': 'status-operativo',
                    'mantenimiento': 'status-mantenimiento',
                    'reparacion': 'status-fuera-servicio'
                };

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${bus.plate}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${bus.model}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${bus.capacity}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${bus.mileage.toLocaleString()} km</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="status-badge ${statusClasses[bus.status]}">${bus.status}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${bus.nextMaintenance}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="editBus(${bus.id})" class="text-blue-600 hover:text-blue-900 mr-3">Editar</button>
                            <button onclick="scheduleMaintenance(${bus.id})" class="text-yellow-600 hover:text-yellow-900 mr-3">Mantenimiento</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Dashboard specific loaders
        function loadPlannerDashboard() {
            // Update planner specific metrics
            document.getElementById('planner-turnos').textContent = '156';
            document.getElementById('planner-cobertura').textContent = '98%';
            document.getElementById('planner-pendientes').textContent = '12';
        }

        function loadProgrammerDashboard() {
            updateProgrammerStats();
            loadOperationsTable();
        }

        function updateProgrammerStats() {
            const disponibles = AppData.conductores.filter(c => c.status === 'disponible').length;
            const enRuta = Math.floor(disponibles * 0.6); // Simulado
            const tardanzas = 3; // Simulado
            const incidencias = 1; // Simulado

            document.getElementById('prog-disponibles').textContent = disponibles;
            document.getElementById('prog-enruta').textContent = enRuta;
            document.getElementById('prog-tardanzas').textContent = tardanzas;
            document.getElementById('prog-incidencias').textContent = incidencias;
        }

        function loadOperationsTable() {
            const container = document.getElementById('operations-table');
            if (!container) return;

            const operations = [
                { conductor: 'Juan Pérez (#C001)', currentRoute: 'Lima-Nazca', departure: '06:00', status: 'En Ruta', nextShift: 'Lima-Chincha 16:00' },
                { conductor: 'Miguel Torres (#C004)', currentRoute: 'Lima-Ica', departure: '08:00', status: 'Programado', nextShift: 'Descanso' },
                { conductor: 'Roberto Silva (#C005)', currentRoute: 'Lima-Pisco', departure: '10:00', status: 'Tardanza', nextShift: 'Lima-Nazca 16:00' },
                { conductor: 'Carlos Gómez (#C002)', currentRoute: 'Vacaciones', departure: '-', status: 'No Disponible', nextShift: 'Retorno 25/01' }
            ];

            container.innerHTML = operations.map(op => {
                const statusColors = {
                    'En Ruta': 'green',
                    'Programado': 'blue',
                    'Tardanza': 'yellow',
                    'No Disponible': 'gray'
                };
                const color = statusColors[op.status];

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${op.conductor}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${op.currentRoute}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${op.departure}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 bg-${color}-100 text-${color}-800 rounded-full text-xs font-medium">${op.status}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${op.nextShift}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button class="text-blue-600 hover:text-blue-900 mr-3">Ver</button>
                            <button class="text-green-600 hover:text-green-900">Asignar</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function loadOperatorDashboard() {
            loadActiveRoutes();
            loadRecentAlerts();
        }

        function loadActiveRoutes() {
            const container = document.getElementById('active-routes');
            if (!container) return;

            const routes = [
                { route: 'Lima-Nazca', conductor: 'Juan Pérez', status: 'En Ruta', progress: 65 },
                { route: 'Lima-Ica', conductor: 'Miguel Torres', status: 'Programado', progress: 0 },
                { route: 'Lima-Pisco', conductor: 'Roberto Silva', status: 'Retrasado', progress: 30 }
            ];

            container.innerHTML = routes.map(route => {
                const statusColors = {
                    'En Ruta': 'green',
                    'Programado': 'blue',
                    'Retrasado': 'red'
                };
                const color = statusColors[route.status];

                return `
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <h5 class="font-medium text-gray-900">${route.route}</h5>
                            <span class="px-2 py-1 bg-${color}-100 text-${color}-800 rounded-full text-xs font-medium">${route.status}</span>
                        </div>
                        <p class="text-sm text-gray-600">${route.conductor}</p>
                        <div class="mt-3">
                            <div class="progress-bar">
                                <div class="progress-fill bg-${color}-500" style="width: ${route.progress}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Progreso: ${route.progress}%</p>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function loadRecentAlerts() {
            const container = document.getElementById('recent-alerts');
            if (!container) return;

            const alerts = [
                { type: 'Tardanza', message: 'Conductor #C005 - 30 min retraso', time: '15 min', severity: 'warning' },
                { type: 'Mecánico', message: 'Bus ABC-123 - Problema motor', time: '1 hora', severity: 'critical' },
                { type: 'Cambio', message: 'Ruta Lima-Ica reasignada', time: '2 horas', severity: 'info' }
            ];

            container.innerHTML = alerts.map(alert => {
                const severityColors = {
                    'critical': 'red',
                    'warning': 'yellow',
                    'info': 'blue'
                };
                const color = severityColors[alert.severity];

                return `
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <h5 class="font-medium text-gray-900">${alert.type}</h5>
                            <span class="text-xs text-gray-500">Hace ${alert.time}</span>
                        </div>
                        <p class="text-sm text-gray-600">${alert.message}</p>
                        <div class="mt-2">
                            <div class="w-full h-1 bg-gray-200 rounded">
                                <div class="h-1 bg-${color}-500 rounded" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Modal Functions
        function showPlanningModal() {
            document.getElementById('planning-modal').classList.add('active');
        }

        function closePlanningModal() {
            document.getElementById('planning-modal').classList.remove('active');
        }

        function savePlanningModal() {
            // Simulate saving
            showNotification('Planificación guardada exitosamente', 'success');
            closePlanningModal();
        }

        function showAlertModal() {
            document.getElementById('alert-modal').classList.add('active');
        }

        function closeAlertModal() {
            document.getElementById('alert-modal').classList.remove('active');
        }

        // Algorithm Functions
        function executeAlgorithm() {
            const statusElement = document.getElementById('algorithm-status');
            const progressElement = document.getElementById('progress-fill');
            const progressText = document.getElementById('progress-text');
            const progressMessage = document.getElementById('algorithm-progress');

            if (statusElement) {
                statusElement.classList.remove('hidden');

                const steps = [
                    { message: 'Validando parámetros del sistema...', progress: 10 },
                    { message: 'Analizando disponibilidad de conductores...', progress: 25 },
                    { message: 'Calculando compatibilidad de rutas...', progress: 45 },
                    { message: 'Optimizando asignaciones...', progress: 70 },
                    { message: 'Aplicando restricciones de descanso...', progress: 85 },
                    { message: 'Finalizando planificación...', progress: 100 }
                ];

                let currentStep = 0;

                const interval = setInterval(() => {
                    if (currentStep < steps.length) {
                        const step = steps[currentStep];
                        if (progressMessage) progressMessage.textContent = step.message;
                        if (progressElement) progressElement.style.width = step.progress + '%';
                        if (progressText) progressText.textContent = step.progress + '%';
                        currentStep++;
                    } else {
                        clearInterval(interval);

                        setTimeout(() => {
                            statusElement.classList.add('hidden');
                            showNotification('Algoritmo ejecutado exitosamente. Planificación actualizada.', 'success');
                            generatePlanningGrid(); // Refresh the grid
                        }, 1000);
                    }
                }, 1500);
            }
        }

        function savePlanning() {
            showNotification('Planificación guardada exitosamente', 'success');
        }

        function exportPlanning() {
            showNotification('Planificación exportada a Excel', 'success');
        }

        // Navigation Functions
        function previousWeek() {
            document.getElementById('current-week').textContent = '08-14 Enero 2024';
            generatePlanningGrid();
        }

        function nextWeek() {
            document.getElementById('current-week').textContent = '22-28 Enero 2024';
            generatePlanningGrid();
        }

        // Utility Functions
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;

            const typeClasses = {
                'success': 'bg-green-500 text-white',
                'error': 'bg-red-500 text-white',
                'warning': 'bg-yellow-500 text-white',
                'info': 'bg-blue-500 text-white'
            };

            notification.classList.add(...typeClasses[type].split(' '));
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : type === 'warning' ? 'exclamation' : 'info'}-circle mr-2"></i>
                    <span>${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            // Animate out and remove
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Action Functions
        function resolveValidation(id) {
            showNotification('Validación resuelta', 'success');
            loadCriticalValidations();
        }

        function postponeValidation(id) {
            showNotification('Validación pospuesta', 'info');
            loadCriticalValidations();
        }

        function editParameter(id) {
            const parameter = AppData.parameters.find(p => p.id === id);
            if (parameter) {
                document.getElementById('param-name').value = parameter.key;
                document.getElementById('param-type').value = parameter.type;
                document.getElementById('param-value').value = parameter.value;
                document.getElementById('param-description').value = parameter.description;
                showNotification('Parámetro cargado para edición', 'info');
            }
        }

        function deleteParameter(id) {
            if (confirm('¿Está seguro de eliminar este parámetro?')) {
                AppData.parameters = AppData.parameters.filter(p => p.id !== id);
                loadParametersTable();
                showNotification('Parámetro eliminado', 'success');
            }
        }

        function editDriver(id) {
            showNotification('Editor de conductor abierto', 'info');
        }

        function assignDriver(id) {
            showNotification('Conductor asignado', 'success');
        }

        function suspendDriver(id) {
            if (confirm('¿Está seguro de suspender este conductor?')) {
                const driver = AppData.conductores.find(c => c.id === id);
                if (driver) {
                    driver.status = 'suspendido';
                    loadDriversTable();
                    updateDriverStats();
                    showNotification('Conductor suspendido', 'success');
                }
            }
        }

        function addDriver() {
            showNotification('Formulario de nuevo conductor abierto', 'info');
        }

        function importDrivers() {
            showNotification('Importador CSV abierto', 'info');
        }

        function filterDrivers() {
            loadDriversTable(); // In a real app, this would apply filters
            showNotification('Filtros aplicados', 'info');
        }

        function addBus() {
            showNotification('Formulario de nuevo bus abierto', 'info');
        }

        function editBus(id) {
            showNotification('Editor de bus abierto', 'info');
        }

        function scheduleMaintenance(id) {
            showNotification('Mantenimiento programado', 'success');
        }

        function addParameter() {
            // Clear form
            document.getElementById('param-name').value = '';
            document.getElementById('param-type').value = 'string';
            document.getElementById('param-value').value = '';
            document.getElementById('param-description').value = '';
            showNotification('Formulario listo para nuevo parámetro', 'info');
        }

        function importParameters() {
            showNotification('Importador CSV abierto', 'info');
        }

        function searchParameters() {
            const query = document.getElementById('param-search').value;
            if (query) {
                showNotification(`Buscando: "${query}"`, 'info');
            }
            loadParametersTable();
        }

        function cancelParameterEdit() {
            document.getElementById('parameter-form').reset();
            showNotification('Edición cancelada', 'info');
        }

        function validateParameter() {
            const name = document.getElementById('param-name').value;
            const type = document.getElementById('param-type').value;
            const value = document.getElementById('param-value').value;

            if (!name || !value) {
                showNotification('Por favor complete todos los campos requeridos', 'error');
                return;
            }

            showNotification('Parámetro validado correctamente', 'success');
        }

        // Form submission handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Parameter form submission
            const parameterForm = document.getElementById('parameter-form');
            if (parameterForm) {
                parameterForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const name = document.getElementById('param-name').value;
                    const type = document.getElementById('param-type').value;
                    const value = document.getElementById('param-value').value;
                    const description = document.getElementById('param-description').value;

                    if (!name || !value) {
                        showNotification('Por favor complete todos los campos requeridos', 'error');
                        return;
                    }

                    // Add to AppData
                    const newParam = {
                        id: AppData.parameters.length + 1,
                        key: name,
                        value: value,
                        type: type,
                        description: description
                    };

                    AppData.parameters.push(newParam);
                    loadParametersTable();
                    parameterForm.reset();
                    showNotification('Parámetro guardado exitosamente', 'success');
                });
            }

            // Alert form submission
            const alertForm = document.getElementById('alert-form');
            if (alertForm) {
                alertForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const type = document.getElementById('alert-type').value;
                    const description = document.getElementById('alert-description').value;
                    const priority = document.getElementById('alert-priority').value;

                    if (!description.trim()) {
                        showNotification('Por favor ingrese una descripción', 'error');
                        return;
                    }

                    closeAlertModal();
                    showNotification(`Alerta ${priority.toUpperCase()} enviada: ${type}`, 'success');
                    alertForm.reset();
                });
            }
        });

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 SIPAT - Sistema de Planificación de Transporte iniciado');

            // Initialize navigation system
            initializeNavigation();

            // Load initial content
            loadAdminDashboard();

            // Start real-time updates
            setInterval(() => {
                // Simulate real-time data updates
                const randomMetric = Math.floor(Math.random() * 100);
                // Update some random metrics occasionally
            }, 30000);

            console.log('✅ Sistema completamente cargado y funcional');
        });

    </script>

</body>
</html>
<?php /**PATH C:\xampp\htdocs\sipat-laravel\resources\views/layouts/app.blade.php ENDPATH**/ ?>
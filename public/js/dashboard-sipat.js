/**
 * =============================================================================
 * DASHBOARD JAVASCRIPT COMPLETO CON CONEXIÓN BACKEND
 * =============================================================================
 * Sistema completo de dashboard con APIs conectadas y funcionalidad real
 */

// =============================================================================
// CONFIGURACIÓN GLOBAL
// =============================================================================

const DashboardConfig = {
    apiBase: '/api/dashboard',
    refreshInterval: 30000, // 30 segundos
    retryAttempts: 3,
    retryDelay: 1000,
    timeout: 10000,
    debug: false
};

// Estado global del dashboard
const DashboardState = {
    currentRole: 'admin',
    currentTab: 'dashboard-admin',
    metricas: {},
    notificaciones: [],
    isLoading: false,
    lastUpdate: null,
    refreshTimer: null,
    conexionEstable: true
};

// =============================================================================
// UTILIDADES Y HELPERS
// =============================================================================

/**
 * Cliente API con retry y manejo de errores
 */
class ApiClient {
    static async request(endpoint, options = {}) {
        const config = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            timeout: DashboardConfig.timeout,
            ...options
        };

        for (let attempt = 1; attempt <= DashboardConfig.retryAttempts; attempt++) {
            try {
                if (DashboardConfig.debug) {
                    console.log(`🌐 API Request [${attempt}/${DashboardConfig.retryAttempts}]:`, endpoint, config);
                }

                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), config.timeout);

                const response = await fetch(`${DashboardConfig.apiBase}${endpoint}`, {
                    ...config,
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (DashboardConfig.debug) {
                    console.log('✅ API Response:', data);
                }

                // Actualizar estado de conexión
                DashboardState.conexionEstable = true;
                this.hideConnectionError();

                return data;

            } catch (error) {
                console.warn(`⚠️ API Request failed (attempt ${attempt}):`, error);

                if (attempt === DashboardConfig.retryAttempts) {
                    DashboardState.conexionEstable = false;
                    this.showConnectionError();
                    throw error;
                }

                // Esperar antes del siguiente intento
                await new Promise(resolve => setTimeout(resolve, DashboardConfig.retryDelay * attempt));
            }
        }
    }

    static async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }

    static async post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    static async put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    static async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }

    static showConnectionError() {
        const existing = document.getElementById('connection-error');
        if (existing) return;

        const errorDiv = document.createElement('div');
        errorDiv.id = 'connection-error';
        errorDiv.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 animate-pulse';
        errorDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-wifi-slash mr-2"></i>
                <span>Problemas de conexión - Reintentando...</span>
            </div>
        `;
        document.body.appendChild(errorDiv);
    }

    static hideConnectionError() {
        const existing = document.getElementById('connection-error');
        if (existing) {
            existing.remove();
        }
    }
}

/**
 * Sistema de loading y estados
 */
class LoadingManager {
    static show(target, message = 'Cargando...') {
        const container = typeof target === 'string' ? document.getElementById(target) : target;
        if (!container) return;

        container.innerHTML = `
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mr-3"></div>
                <span class="text-gray-600">${message}</span>
            </div>
        `;
    }

    static hide(target) {
        // El contenido se reemplaza cuando se cargan los datos reales
    }

    static showError(target, message, retry = null) {
        const container = typeof target === 'string' ? document.getElementById(target) : target;
        if (!container) return;

        const retryButton = retry ? `
            <button onclick="${retry}" class="mt-3 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>Reintentar
            </button>
        ` : '';

        container.innerHTML = `
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-3"></i>
                <p class="text-gray-600 mb-3">${message}</p>
                ${retryButton}
            </div>
        `;
    }
}

/**
 * Utilidades de formato
 */
class FormatUtils {
    static number(value, decimals = 0) {
        return new Intl.NumberFormat('es-PE', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(value || 0);
    }

    static currency(value) {
        return new Intl.NumberFormat('es-PE', {
            style: 'currency',
            currency: 'PEN'
        }).format(value || 0);
    }

    static percentage(value) {
        return `${this.number(value, 1)}%`;
    }

    static timeAgo(date) {
        const now = new Date();
        const past = new Date(date);
        const diffMs = now - past;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'Ahora mismo';
        if (diffMins < 60) return `Hace ${diffMins} min`;
        if (diffHours < 24) return `Hace ${diffHours}h`;
        return `Hace ${diffDays} días`;
    }
}

// =============================================================================
// FUNCIONES PRINCIPALES DEL DASHBOARD
// =============================================================================

/**
 * Cargar dashboard de administrador
 */
async function loadAdminDashboard() {
    try {
        DashboardState.isLoading = true;

        // Mostrar loading en diferentes secciones
        LoadingManager.show('admin-metrics', 'Cargando métricas...');
        LoadingManager.show('admin-charts', 'Cargando gráficos...');
        LoadingManager.show('admin-alerts', 'Cargando alertas...');

        // Cargar datos en paralelo
        const [metricas, graficos, alertas] = await Promise.all([
            ApiClient.get('/metricas'),
            ApiClient.get('/chart-data'),
            ApiClient.get('/alertas')
        ]);

        // Actualizar métricas principales
        updateAdminMetrics(metricas.data);

        // Actualizar gráficos
        updateAdminCharts(graficos.data);

        // Actualizar alertas
        updateAdminAlerts(alertas.data);

        // Guardar en estado global
        DashboardState.metricas = metricas.data;
        DashboardState.lastUpdate = new Date();

        console.log('✅ Dashboard Admin cargado exitosamente');

    } catch (error) {
        console.error('❌ Error cargando dashboard admin:', error);

        LoadingManager.showError('admin-metrics', 'Error cargando métricas', 'loadAdminDashboard()');
        LoadingManager.showError('admin-charts', 'Error cargando gráficos', 'loadAdminDashboard()');
        LoadingManager.showError('admin-alerts', 'Error cargando alertas', 'loadAdminDashboard()');

        showNotification('Error cargando dashboard', 'error');
    } finally {
        DashboardState.isLoading = false;
    }
}

/**
 * Actualizar métricas del dashboard admin
 */
function updateAdminMetrics(data) {
    const metricsContainer = document.getElementById('admin-metrics');
    if (!metricsContainer) return;

    metricsContainer.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Métrica Conductores -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Conductores Activos</p>
                        <h3 class="text-3xl font-bold text-blue-600">${FormatUtils.number(data.conductores?.disponibles || 0)}</h3>
                        <p class="text-sm text-gray-500">de ${FormatUtils.number(data.conductores?.total || 0)} total</p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex items-center text-sm">
                        <span class="text-red-600">🚨 ${data.conductores?.criticos || 0} críticos</span>
                    </div>
                </div>
            </div>

            <!-- Métrica Validaciones -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Validaciones</p>
                        <h3 class="text-3xl font-bold text-orange-600">${FormatUtils.number(data.validaciones?.pendientes || 0)}</h3>
                        <p class="text-sm text-gray-500">pendientes</p>
                    </div>
                    <div class="p-3 bg-orange-100 rounded-full">
                        <i class="fas fa-clipboard-check text-orange-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex items-center text-sm">
                        <span class="text-red-600">⚠️ ${data.validaciones?.criticas || 0} críticas</span>
                    </div>
                </div>
            </div>

            <!-- Métrica Rutas -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Rutas Hoy</p>
                        <h3 class="text-3xl font-bold text-green-600">${FormatUtils.number(data.rutas?.completadas_hoy || 0)}</h3>
                        <p class="text-sm text-gray-500">completadas</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-route text-green-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex items-center text-sm">
                        <span class="text-green-600">💰 ${FormatUtils.currency(data.rutas?.ingresos || 0)}</span>
                    </div>
                </div>
            </div>

            <!-- Métrica Rendimiento -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Rendimiento</p>
                        <h3 class="text-3xl font-bold text-purple-600">${FormatUtils.percentage(data.rendimiento?.eficiencia_promedio || 0)}</h3>
                        <p class="text-sm text-gray-500">eficiencia promedio</p>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex items-center text-sm">
                        <span class="text-blue-600">⏰ ${FormatUtils.percentage(data.rendimiento?.puntualidad_promedio || 0)} puntualidad</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Actualizar gráficos del dashboard admin
 */
function updateAdminCharts(data) {
    const chartsContainer = document.getElementById('admin-charts');
    if (!chartsContainer) return;

    chartsContainer.innerHTML = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Gráfico de Tendencias -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Tendencias Semanales</h3>
                <canvas id="trends-chart"></canvas>
            </div>

            <!-- Gráfico de Distribución -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Distribución de Conductores</h3>
                <canvas id="distribution-chart"></canvas>
            </div>
        </div>
    `;

    // Crear gráficos con Chart.js
    createTrendsChart(data.tendencias || {});
    createDistributionChart(data.distribucion || {});
}

/**
 * Cargar dashboard de planificador
 */
async function loadPlannerDashboard() {
    try {
        LoadingManager.show('planner-content', 'Cargando panel de planificación...');

        const data = await ApiClient.get('/planner-data');

        const container = document.getElementById('planner-content');
        if (container) {
            container.innerHTML = `
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Métricas de Planificación -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Estado Actual</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span>Conductores Disponibles:</span>
                                <span class="font-bold text-green-600">${data.data?.disponibles || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Turnos Sin Asignar:</span>
                                <span class="font-bold text-red-600">${data.data?.sin_asignar || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Próximos Descansos:</span>
                                <span class="font-bold text-orange-600">${data.data?.proximos_descansos || 0}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones Rápidas -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Acciones Rápidas</h3>
                        <div class="space-y-3">
                            <button onclick="executeAlgorithm()" class="w-full py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                                <i class="fas fa-play mr-2"></i>Ejecutar Algoritmo
                            </button>
                            <button onclick="showPlanningModal()" class="w-full py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
                                <i class="fas fa-calendar-plus mr-2"></i>Nueva Planificación
                            </button>
                            <button onclick="exportPlanning()" class="w-full py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition-colors">
                                <i class="fas fa-download mr-2"></i>Exportar Datos
                            </button>
                        </div>
                    </div>

                    <!-- Estado del Sistema -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Sistema</h3>
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                <span>Operativo</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                Última actualización: ${FormatUtils.timeAgo(DashboardState.lastUpdate || new Date())}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Planificación -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Planificación Actual</h3>
                    <div id="planning-table">
                        ${generatePlanningTable(data.data?.planificacion || [])}
                    </div>
                </div>
            `;
        }

    } catch (error) {
        console.error('❌ Error cargando dashboard planificador:', error);
        LoadingManager.showError('planner-content', 'Error cargando panel de planificación', 'loadPlannerDashboard()');
    }
}

/**
 * Ejecutar algoritmo de planificación
 */
async function executeAlgorithm() {
    try {
        // Mostrar modal de confirmación
        const result = await Swal.fire({
            title: '🤖 Ejecutar Algoritmo de Planificación',
            text: '¿Está seguro que desea ejecutar el algoritmo automático?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Sí, ejecutar',
            cancelButtonText: 'Cancelar',
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                try {
                    const response = await ApiClient.post('/ejecutar-algoritmo', {
                        fecha: new Date().toISOString().split('T')[0],
                        modo: 'automatico'
                    });
                    return response;
                } catch (error) {
                    Swal.showValidationMessage(`Error: ${error.message}`);
                }
            },
            allowOutsideClick: () => !Swal.isLoading()
        });

        if (result.isConfirmed) {
            const data = result.value.data;

            Swal.fire({
                title: '✅ Algoritmo Ejecutado',
                html: `
                    <div class="text-left">
                        <p><strong>Conductores procesados:</strong> ${data.conductores_procesados || 0}</p>
                        <p><strong>Asignaciones realizadas:</strong> ${data.asignaciones_realizadas || 0}</p>
                        <p><strong>Validaciones generadas:</strong> ${data.validaciones_generadas || 0}</p>
                        <p><strong>Tiempo de procesamiento:</strong> ${data.tiempo_procesamiento || 0}s</p>
                    </div>
                `,
                icon: 'success',
                confirmButtonText: 'Entendido'
            });

            // Recargar dashboard
            loadPlannerDashboard();
        }

    } catch (error) {
        console.error('❌ Error ejecutando algoritmo:', error);

        Swal.fire({
            title: '❌ Error',
            text: 'No se pudo ejecutar el algoritmo. Intente nuevamente.',
            icon: 'error',
            confirmButtonText: 'Entendido'
        });
    }
}

/**
 * Cargar notificaciones
 */
async function loadNotifications() {
    try {
        const response = await ApiClient.get('/notificaciones');
        const notificaciones = response.data || [];

        DashboardState.notificaciones = notificaciones;

        const container = document.getElementById('notifications-list');
        if (container) {
            if (notificaciones.length === 0) {
                container.innerHTML = `
                    <div class="p-4 text-center text-gray-500">
                        <i class="fas fa-bell-slash text-2xl mb-2"></i>
                        <p>No hay notificaciones</p>
                    </div>
                `;
            } else {
                container.innerHTML = notificaciones.map(notif => `
                    <div class="p-3 border-b border-gray-200 hover:bg-gray-50 cursor-pointer ${!notif.leida ? 'bg-blue-50' : ''}"
                         onclick="markNotificationAsRead(${notif.id})">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-3">
                                <div class="w-2 h-2 mt-2 rounded-full ${!notif.leida ? 'bg-blue-500' : 'bg-gray-300'}"></div>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-gray-800">${notif.titulo}</p>
                                <p class="text-sm text-gray-600">${notif.mensaje}</p>
                                <p class="text-xs text-gray-400 mt-1">${FormatUtils.timeAgo(notif.created_at)}</p>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
        }

        // Actualizar contador
        updateNotificationBadge(notificaciones.filter(n => !n.leida).length);

    } catch (error) {
        console.error('❌ Error cargando notificaciones:', error);

        const container = document.getElementById('notifications-list');
        if (container) {
            container.innerHTML = `
                <div class="p-4 text-center text-red-500">
                    <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                    <p>Error cargando notificaciones</p>
                </div>
            `;
        }
    }
}

/**
 * Marcar notificación como leída
 */
async function markNotificationAsRead(notificationId) {
    try {
        await ApiClient.put(`/notificaciones/${notificationId}/leer`);

        // Actualizar estado local
        const notification = DashboardState.notificaciones.find(n => n.id === notificationId);
        if (notification) {
            notification.leida = true;
        }

        // Recargar notificaciones
        loadNotifications();

    } catch (error) {
        console.error('❌ Error marcando notificación como leída:', error);
    }
}

/**
 * Actualizar badge de notificaciones
 */
function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
}

/**
 * Exportar planificación
 */
async function exportPlanning() {
    try {
        const result = await Swal.fire({
            title: '📊 Exportar Planificación',
            html: `
                <div class="text-left">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Formato:</label>
                    <select id="export-format" class="w-full border border-gray-300 rounded px-3 py-2 mb-4">
                        <option value="excel">Excel (.xlsx)</option>
                        <option value="pdf">PDF</option>
                        <option value="csv">CSV</option>
                    </select>

                    <label class="block text-sm font-medium text-gray-700 mb-2">Período:</label>
                    <select id="export-period" class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="hoy">Solo hoy</option>
                        <option value="semana">Esta semana</option>
                        <option value="mes">Este mes</option>
                    </select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Exportar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const format = document.getElementById('export-format').value;
                const period = document.getElementById('export-period').value;
                return { format, period };
            }
        });

        if (result.isConfirmed) {
            const { format, period } = result.value;

            Swal.fire({
                title: 'Generando reporte...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await ApiClient.post('/exportar-planificacion', { format, period });

            // Descargar archivo
            if (response.success && response.data.url) {
                window.open(response.data.url, '_blank');

                Swal.fire({
                    title: '✅ Exportación Completa',
                    text: 'El archivo se está descargando.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                throw new Error('No se pudo generar el archivo');
            }
        }

    } catch (error) {
        console.error('❌ Error exportando planificación:', error);

        Swal.fire({
            title: '❌ Error',
            text: 'No se pudo exportar la planificación. Intente nuevamente.',
            icon: 'error'
        });
    }
}

/**
 * Mostrar modal de nueva planificación
 */
function showPlanningModal() {
    Swal.fire({
        title: '📅 Nueva Planificación',
        html: `
            <div class="text-left space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fecha de servicio:</label>
                    <input type="date" id="planning-date" class="w-full border border-gray-300 rounded px-3 py-2"
                           value="${new Date().toISOString().split('T')[0]}">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de planificación:</label>
                    <select id="planning-type" class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="automatica">Automática (IA)</option>
                        <option value="semiautomatica">Semi-automática</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones:</label>
                    <textarea id="planning-notes" class="w-full border border-gray-300 rounded px-3 py-2"
                              rows="3" placeholder="Observaciones opcionales..."></textarea>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Crear Planificación',
        cancelButtonText: 'Cancelar',
        width: '500px',
        preConfirm: () => {
            const fecha = document.getElementById('planning-date').value;
            const tipo = document.getElementById('planning-type').value;
            const notas = document.getElementById('planning-notes').value;

            if (!fecha) {
                Swal.showValidationMessage('Debe seleccionar una fecha');
                return false;
            }

            return { fecha, tipo, notas };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await ApiClient.post('/crear-planificacion', result.value);

                Swal.fire({
                    title: '✅ Planificación Creada',
                    text: 'La planificación se ha creado exitosamente.',
                    icon: 'success'
                });

                // Recargar dashboard
                loadPlannerDashboard();

            } catch (error) {
                Swal.fire({
                    title: '❌ Error',
                    text: 'No se pudo crear la planificación.',
                    icon: 'error'
                });
            }
        }
    });
}

/**
 * Generar tabla de planificación
 */
function generatePlanningTable(data) {
    if (!data || data.length === 0) {
        return `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-calendar-times text-3xl mb-3"></i>
                <p>No hay planificación disponible para hoy</p>
                <button onclick="showPlanningModal()" class="mt-3 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Crear Nueva Planificación
                </button>
            </div>
        `;
    }

    const rows = data.map(item => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm">${item.hora_salida || 'N/A'}</td>
            <td class="px-4 py-3 text-sm">${item.conductor || 'Sin asignar'}</td>
            <td class="px-4 py-3 text-sm">${item.origen || 'N/A'}</td>
            <td class="px-4 py-3 text-sm">${item.destino || 'N/A'}</td>
            <td class="px-4 py-3 text-sm">
                <span class="px-2 py-1 rounded text-xs ${getStatusColor(item.estado)}">
                    ${item.estado || 'Pendiente'}
                </span>
            </td>
            <td class="px-4 py-3 text-sm">
                <button onclick="editPlanningItem(${item.id})" class="text-blue-600 hover:text-blue-800 mr-2">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="deletePlanningItem(${item.id})" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');

    return `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hora</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conductor</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origen</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destino</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${rows}
                </tbody>
            </table>
        </div>
    `;
}

/**
 * Obtener color según estado
 */
function getStatusColor(estado) {
    const colors = {
        'completado': 'bg-green-100 text-green-800',
        'en_curso': 'bg-blue-100 text-blue-800',
        'pendiente': 'bg-yellow-100 text-yellow-800',
        'cancelado': 'bg-red-100 text-red-800'
    };
    return colors[estado?.toLowerCase()] || 'bg-gray-100 text-gray-800';
}

/**
 * Mostrar notificación temporal
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform transition-transform duration-300 translate-x-full`;

    const colors = {
        'success': 'bg-green-500 text-white',
        'error': 'bg-red-500 text-white',
        'warning': 'bg-yellow-500 text-white',
        'info': 'bg-blue-500 text-white'
    };

    notification.className += ` ${colors[type] || colors.info}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle mr-2"></i>
            <span>${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    // Animar entrada
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);

    // Remover después de 3 segundos
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

/**
 * Crear gráfico de tendencias
 */
function createTrendsChart(data) {
    const ctx = document.getElementById('trends-chart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            datasets: [{
                label: 'Eficiencia',
                data: data.eficiencia || [85, 87, 82, 90, 88, 86, 89],
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.1
            }, {
                label: 'Puntualidad',
                data: data.puntualidad || [92, 90, 94, 89, 91, 93, 95],
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
}

/**
 * Crear gráfico de distribución
 */
function createDistributionChart(data) {
    const ctx = document.getElementById('distribution-chart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || ['Disponibles', 'En Descanso', 'Críticos', 'Inactivos'],
            datasets: [{
                data: data.values || [45, 12, 3, 5],
                backgroundColor: [
                    'rgb(34, 197, 94)',
                    'rgb(251, 191, 36)',
                    'rgb(239, 68, 68)',
                    'rgb(156, 163, 175)'
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
}

// =============================================================================
// SISTEMA DE ACTUALIZACIÓN AUTOMÁTICA
// =============================================================================

/**
 * Iniciar actualización automática
 */
function startAutoRefresh() {
    if (DashboardState.refreshTimer) {
        clearInterval(DashboardState.refreshTimer);
    }

    DashboardState.refreshTimer = setInterval(async () => {
        if (!DashboardState.isLoading && DashboardState.conexionEstable) {
            try {
                await refreshCurrentDashboard();
            } catch (error) {
                console.warn('⚠️ Error en actualización automática:', error);
            }
        }
    }, DashboardConfig.refreshInterval);

    console.log(`🔄 Auto-refresh iniciado (cada ${DashboardConfig.refreshInterval/1000}s)`);
}

/**
 * Refrescar dashboard actual
 */
async function refreshCurrentDashboard() {
    switch (DashboardState.currentTab) {
        case 'dashboard-admin':
            await loadAdminDashboard();
            break;
        case 'dashboard-planner':
            await loadPlannerDashboard();
            break;
        // Agregar otros dashboards según sea necesario
    }

    // Recargar notificaciones
    await loadNotifications();
}

/**
 * Detener actualización automática
 */
function stopAutoRefresh() {
    if (DashboardState.refreshTimer) {
        clearInterval(DashboardState.refreshTimer);
        DashboardState.refreshTimer = null;
        console.log('⏹️ Auto-refresh detenido');
    }
}

// =============================================================================
// INICIALIZACIÓN
// =============================================================================

/**
 * Inicializar dashboard al cargar la página
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando Dashboard SIPAT...');

    // Configurar CSRF token para todas las peticiones AJAX
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        window.axios = window.axios || {};
        window.axios.defaults = window.axios.defaults || {};
        window.axios.defaults.headers = window.axios.defaults.headers || {};
        window.axios.defaults.headers.common = window.axios.defaults.headers.common || {};
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
    }

    // Cargar dashboard inicial según el rol
    const userRole = document.body.getAttribute('data-user-role') || 'admin';
    DashboardState.currentRole = userRole;

    // Cargar contenido inicial
    switch (userRole) {
        case 'admin':
            DashboardState.currentTab = 'dashboard-admin';
            loadAdminDashboard();
            break;
        case 'planner':
            DashboardState.currentTab = 'dashboard-planner';
            loadPlannerDashboard();
            break;
        default:
            DashboardState.currentTab = 'dashboard-admin';
            loadAdminDashboard();
    }

    // Cargar notificaciones
    loadNotifications();

    // Iniciar actualización automática
    startAutoRefresh();

    // Configurar event listeners
    setupEventListeners();

    console.log('✅ Dashboard SIPAT inicializado correctamente');
});

/**
 * Configurar event listeners
 */
function setupEventListeners() {
    // Escuchar cambios de pestaña
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }
    });

    // Manejar errores globales de JavaScript
    window.addEventListener('error', function(e) {
        console.error('❌ Error JavaScript:', e.error);

        if (DashboardConfig.debug) {
            showNotification('Error en la aplicación', 'error');
        }
    });

    // Manejar errores de promesas no capturadas
    window.addEventListener('unhandledrejection', function(e) {
        console.error('❌ Error Promise no manejada:', e.reason);
        e.preventDefault();
    });
}

// Limpiar al salir de la página
window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});

// Exponer funciones globales necesarias
window.DashboardSIPAT = {
    loadAdminDashboard,
    loadPlannerDashboard,
    executeAlgorithm,
    exportPlanning,
    showPlanningModal,
    loadNotifications,
    markNotificationAsRead,
    startAutoRefresh,
    stopAutoRefresh,
    ApiClient,
    DashboardState,
    DashboardConfig
};

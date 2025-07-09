<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title>Login - SIPAT</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Colores SIPAT */
            --sipat-primary: #3B82F6;
            --sipat-primary-dark: #2563EB;
            --sipat-secondary: #64748B;
            --sipat-success: #10B981;
            --sipat-danger: #EF4444;
            --sipat-warning: #F59E0B;
            --sipat-info: #06B6D4;

            /* Grises */
            --sipat-gray-50: #F8FAFC;
            --sipat-gray-100: #F1F5F9;
            --sipat-gray-200: #E2E8F0;
            --sipat-gray-300: #CBD5E1;
            --sipat-gray-400: #94A3B8;
            --sipat-gray-500: #64748B;
            --sipat-gray-600: #475569;
            --sipat-gray-700: #334155;
            --sipat-gray-800: #1E293B;
            --sipat-gray-900: #0F172A;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--sipat-primary) 0%, var(--sipat-primary-dark) 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            padding: 3rem;
            width: 100%;
            max-width: 420px;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--sipat-primary), var(--sipat-info), var(--sipat-success));
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--sipat-primary), var(--sipat-primary-dark));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
        }

        .login-logo i {
            font-size: 2.5rem;
            color: white;
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--sipat-gray-800);
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: var(--sipat-gray-600);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--sipat-gray-700);
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            border: 2px solid var(--sipat-gray-200);
            border-radius: 12px;
            padding: 0.9rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--sipat-gray-50);
        }

        .form-control:focus {
            border-color: var(--sipat-primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--sipat-gray-400);
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--sipat-gray-500);
            z-index: 10;
            pointer-events: none;
        }

        .input-group .form-control {
            padding-left: 3rem;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--sipat-primary), var(--sipat-primary-dark));
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            transform: none;
            cursor: not-allowed;
        }

        .form-check {
            margin: 1.5rem 0;
        }

        .form-check-input {
            width: 1.2rem;
            height: 1.2rem;
            border: 2px solid var(--sipat-gray-300);
            border-radius: 4px;
        }

        .form-check-input:checked {
            background-color: var(--sipat-primary);
            border-color: var(--sipat-primary);
        }

        .form-check-label {
            font-size: 0.9rem;
            color: var(--sipat-gray-600);
            margin-left: 0.5rem;
        }

        .alert {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: none;
            font-size: 0.9rem;
        }

        .alert-danger {
            background: #FEF2F2;
            color: #B91C1C;
            border-left: 4px solid var(--sipat-danger);
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--sipat-gray-200);
        }

        .login-footer p {
            color: var(--sipat-gray-500);
            font-size: 0.85rem;
            margin: 0;
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        @media (max-width: 576px) {
            .login-card {
                margin: 1rem;
                padding: 2rem 1.5rem;
            }

            .login-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Partículas flotantes decorativas -->
    <div class="floating-particles">
        <div class="particle" style="left: 10%; width: 8px; height: 8px; animation-delay: 0s;"></div>
        <div class="particle" style="left: 20%; width: 12px; height: 12px; animation-delay: 1s;"></div>
        <div class="particle" style="left: 30%; width: 6px; height: 6px; animation-delay: 2s;"></div>
        <div class="particle" style="left: 40%; width: 10px; height: 10px; animation-delay: 3s;"></div>
        <div class="particle" style="left: 60%; width: 8px; height: 8px; animation-delay: 4s;"></div>
        <div class="particle" style="left: 70%; width: 12px; height: 12px; animation-delay: 5s;"></div>
        <div class="particle" style="left: 80%; width: 6px; height: 6px; animation-delay: 6s;"></div>
        <div class="particle" style="left: 90%; width: 10px; height: 10px; animation-delay: 7s;"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-bus"></i>
                </div>
                <h1 class="login-title">SIPAT</h1>
                <p class="login-subtitle">Sistema Integral de Planificación Automatizada de Transportes</p>
            </div>

            <!-- Mostrar errores de validación -->
            <?php if($errors->any()): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php if($errors->has('email')): ?>
                        <?php echo e($errors->first('email')); ?>

                    <?php elseif($errors->has('password')): ?>
                        <?php echo e($errors->first('password')); ?>

                    <?php else: ?>
                        <?php echo e($errors->first()); ?>

                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Mensaje de sesión expirada -->
            <?php if(session('status')): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php echo e(session('status')); ?>

                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo e(route('login')); ?>" id="loginForm">
                <?php echo csrf_field(); ?>

                <!-- Campo Email -->
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-2"></i>Email
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input
                            id="email"
                            type="email"
                            class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                            name="email"
                            value="<?php echo e(old('email')); ?>"
                            required
                            autocomplete="email"
                            autofocus
                            placeholder="admin@sipat.com">
                    </div>
                </div>

                <!-- Campo Password -->
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Contraseña
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input
                            id="password"
                            type="password"
                            class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••">
                    </div>
                </div>

                <!-- Recordarme -->
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" <?php echo e(old('remember') ? 'checked' : ''); ?>>
                    <label class="form-check-label" for="remember">
                        Recordar sesión
                    </label>
                </div>

                <!-- Botón de Login -->
                <button type="submit" class="btn btn-login" id="loginButton">
                    <span id="loginText">
                        <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                    </span>
                    <div class="loading-spinner" id="loadingSpinner"></div>
                </button>
            </form>

            <div class="login-footer">
                <p>
                    <i class="fas fa-shield-alt me-1"></i>
                    Acceso seguro y protegido
                </p>
                <p class="mt-2">
                    <strong>Usuarios de prueba:</strong><br>
                    <small>admin@sipat.com / sipat2025</small><br>
                    <small>operador@sipat.com / operador2025</small>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const button = document.getElementById('loginButton');
            const loginText = document.getElementById('loginText');
            const loadingSpinner = document.getElementById('loadingSpinner');

            // Mostrar estado de carga
            button.disabled = true;
            loginText.style.display = 'none';
            loadingSpinner.style.display = 'inline-block';

            // Simular delay mínimo para UX
            setTimeout(() => {
                // El formulario se enviará normalmente
            }, 500);
        });

        // Animación de partículas
        function createParticle() {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = '100%';
            particle.style.width = (Math.random() * 8 + 4) + 'px';
            particle.style.height = particle.style.width;
            particle.style.animationDuration = (Math.random() * 3 + 3) + 's';
            particle.style.opacity = Math.random() * 0.3 + 0.1;

            document.querySelector('.floating-particles').appendChild(particle);

            setTimeout(() => {
                particle.remove();
            }, 6000);
        }

        // Crear partículas periódicamente
        setInterval(createParticle, 2000);

        // Auto-focus en el primer campo vacío
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');

            if (!emailField.value) {
                emailField.focus();
            } else if (!passwordField.value) {
                passwordField.focus();
            }
        });

        // Manejar Enter en los campos
        document.getElementById('email').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('password').focus();
            }
        });

        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\sipat-laravel\resources\views/auth/login.blade.php ENDPATH**/ ?>
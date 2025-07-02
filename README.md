<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

```
sipat-laravel
├─ .editorconfig
├─ app
│  ├─ Console
│  │  ├─ Commands
│  │  │  └─ EjecutarValidaciones.php
│  │  └─ Kernel.php
│  ├─ Exceptions
│  │  └─ Handler.php
│  ├─ Http
│  │  ├─ Controllers
│  │  │  ├─ ConductorController.php
│  │  │  ├─ Controller.php
│  │  │  ├─ DashboardController.php
│  │  │  ├─ DescansoController.php
│  │  │  ├─ ParametroController.php
│  │  │  ├─ PlantillaController.php
│  │  │  ├─ PlantillaPDFController.php
│  │  │  ├─ ReplanificacionController.php
│  │  │  ├─ ReporteController.php
│  │  │  ├─ RutaCortaController.php
│  │  │  ├─ SubempresaController.php
│  │  │  └─ ValidacionController.php
│  │  ├─ Kernel.php
│  │  ├─ Middleware
│  │  │  ├─ Authenticate.php
│  │  │  ├─ EncryptCookies.php
│  │  │  ├─ PreventRequestsDuringMaintenance.php
│  │  │  ├─ RedirectIfAuthenticated.php
│  │  │  ├─ ShareValidacionesCount.php
│  │  │  ├─ TrimStrings.php
│  │  │  ├─ TrustHosts.php
│  │  │  ├─ TrustProxies.php
│  │  │  ├─ ValidateSignature.php
│  │  │  └─ VerifyCsrfToken.php
│  │  └─ ShareValidacionesCount.php
│  ├─ Imports
│  │  └─ ConductoresImport.php
│  ├─ Models
│  │  ├─ BalanceRutasCortas.php
│  │  ├─ Bus.php
│  │  ├─ Conductor.php
│  │  ├─ ConfiguracionTramo.php
│  │  ├─ MetricaDiaria.php
│  │  ├─ Parametro.php
│  │  ├─ ParametroPredictivo.php
│  │  ├─ Plantilla.php
│  │  ├─ RutaCorta.php
│  │  ├─ Turno.php
│  │  ├─ User.php
│  │  └─ Validacion.php
│  └─ Providers
│     ├─ AppServiceProvider.php
│     ├─ AuthServiceProvider.php
│     ├─ BroadcastServiceProvider.php
│     ├─ EventServiceProvider.php
│     └─ RouteServiceProvider.php
├─ artisan
├─ bootstrap
│  ├─ app.php
│  └─ cache
│     ├─ packages.php
│     └─ services.php
├─ composer.json
├─ composer.lock
├─ config
│  ├─ app.php
│  ├─ auth.php
│  ├─ broadcasting.php
│  ├─ cache.php
│  ├─ cors.php
│  ├─ database.php
│  ├─ dompdf.php
│  ├─ filesystems.php
│  ├─ hashing.php
│  ├─ logging.php
│  ├─ mail.php
│  ├─ queue.php
│  ├─ sanctum.php
│  ├─ services.php
│  ├─ session.php
│  └─ view.php
├─ database
│  ├─ factories
│  │  └─ UserFactory.php
│  ├─ migrations
│  │  ├─ 2014_10_12_000000_create_users_table.php
│  │  ├─ 2014_10_12_100000_create_password_resets_table.php
│  │  ├─ 2019_08_19_000000_create_failed_jobs_table.php
│  │  ├─ 2019_12_14_000001_create_personal_access_tokens_table.php
│  │  ├─ 2025_06_30_082457_create_conductores_table.php
│  │  ├─ 2025_06_30_082511_create_validaciones_table.php
│  │  ├─ 2025_06_30_082517_create_plantillas_table.php
│  │  ├─ 2025_06_30_082522_create_turnos_table.php
│  │  ├─ 2025_06_30_082529_create_buses_table.php
│  │  ├─ 2025_06_30_082534_create_parametros_table.php
│  │  ├─ 2025_06_30_082540_create_metricas_diarias_table.php
│  │  ├─ 2025_06_30_100119_create_rutas_cortas_table.php
│  │  ├─ 2025_06_30_100551_create_configuracion_tramos_table.php
│  │  ├─ 2025_06_30_100613_create_balance_rutas_cortas_table.php
│  │  ├─ 2025_06_30_122235_add_new_columns_to_conductores_table.php
│  │  ├─ 2025_06_30_123234_create_plantilla_turnos_table.php
│  │  ├─ 2025_06_30_124034_create_subempresa_frecuencias_table.php
│  │  ├─ 2025_06_30_124041_create_subempresa_asignaciones_table.php
│  │  ├─ 2025_07_01_010544_create_planificacion_descansos_table.php
│  │  └─ 2025_07_01_011653_create_conductores_backup_table.php
│  └─ seeders
│     ├─ BusSeeder.php
│     ├─ ConductorSeeder.php
│     ├─ DatabaseSeeder.php
│     ├─ ParametroSeeder.php
│     ├─ RutasCortasEjemploSeeder.php
│     └─ RutasCortasSeeder.php
├─ lang
│  └─ en
│     ├─ auth.php
│     ├─ pagination.php
│     ├─ passwords.php
│     └─ validation.php
├─ name('validaciones.index')
├─ package-lock.json
├─ package.json
├─ php
├─ phpunit.xml
├─ prepareBindings($bindings)
├─ public
│  ├─ .htaccess
│  ├─ favicon.ico
│  ├─ index.php
│  └─ robots.txt
├─ README.md
├─ resources
│  ├─ css
│  │  └─ app.css
│  ├─ js
│  │  ├─ app.js
│  │  └─ bootstrap.js
│  ├─ sass
│  │  ├─ app.scss
│  │  └─ _variables.scss
│  └─ views
│     ├─ conductores
│     │  ├─ create.blade.php
│     │  ├─ edit.blade.php
│     │  ├─ index.blade.php
│     │  └─ show.blade.php
│     ├─ dashboard
│     │  └─ index.blade.php
│     ├─ layouts
│     │  └─ app.blade.php
│     ├─ parametros
│     │  └─ index.blade.php
│     ├─ plantillas
│     │  └─ index.blade.php
│     ├─ reportes
│     │  └─ index.blade.php
│     ├─ rutas-cortas
│     │  ├─ configuracion
│     │  │  └─ edit.blade.php
│     │  ├─ create.blade.php
│     │  ├─ index.blade.php
│     │  ├─ reporte-conductor.blade.php
│     │  └─ show.blade.php
│     ├─ validaciones
│     │  └─ index.blade.php
│     └─ welcome.blade.php
├─ routes
│  ├─ api.php
│  ├─ channels.php
│  ├─ console.php
│  └─ web.php
├─ storage
│  ├─ app
│  │  └─ public
│  ├─ framework
│  │  ├─ cache
│  │  │  └─ data
│  │  ├─ sessions
│  │  │  ├─ jlKEbd4bmcCmnCAmlGsfKqLAblIAPg7VUGBiHSzH
│  │  │  └─ YXrid3BHaESMGcSIKNdCZpNnYt0oBIH04sB1pTTC
│  │  ├─ testing
│  │  └─ views
│  │     ├─ 11b26d48ca05ca1bdcc54205025f2430a9ae3423.php
│  │     ├─ 18474e77ce4ba4a5060f6428060485dfa9403e08.php
│  │     ├─ 27a802d9ed2d090909b642a887dfd018794bb194.php
│  │     ├─ 4100f9537f7d8010fd4d24fa1ea3ebb0c46457a5.php
│  │     ├─ 977f10e2b9be45755f8fa5757dd7b8bdec1c8eec.php
│  │     ├─ 98ddb504a11e46a2ba9499f4622579c8261bf9da.php
│  │     ├─ e1559791ff558be24db3a6f696aa3ae35590e214.php
│  │     └─ f53e0b9628a601df95b0db4e70212971e17696da.php
│  └─ logs
│     └─ laravel.log
├─ tests
│  ├─ CreatesApplication.php
│  ├─ Feature
│  │  └─ ExampleTest.php
│  ├─ TestCase.php
│  └─ Unit
│     └─ ExampleTest.php
└─ vite.config.js

```

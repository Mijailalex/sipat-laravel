<?php

namespace Database\Factories;

use App\Models\Parametro;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Parametro>
 */
class ParametroFactory extends Factory
{
    protected $model = Parametro::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $tipos = ['STRING', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'JSON', 'DATE', 'TIME'];
        $tipo = $this->faker->randomElement($tipos);

        return [
            'categoria' => strtoupper($this->faker->word()),
            'clave' => strtolower($this->faker->unique()->slug(2, '_')),
            'nombre' => $this->faker->sentence(3),
            'descripcion' => $this->faker->optional()->paragraph(),
            'tipo' => $tipo,
            'valor' => $this->generateValueForType($tipo),
            'valor_por_defecto' => $this->generateValueForType($tipo),
            'opciones' => $this->faker->optional()->passthrough(
                json_encode($this->faker->words(3))
            ),
            'modificable' => $this->faker->boolean(80), // 80% modificables
            'visible_interfaz' => $this->faker->boolean(90), // 90% visibles
            'orden_visualizacion' => $this->faker->numberBetween(0, 100),
            'modificado_por' => User::factory(),
        ];
    }

    /**
     * Generate value based on type
     */
    private function generateValueForType(string $tipo): string
    {
        switch ($tipo) {
            case 'INTEGER':
                return (string) $this->faker->numberBetween(1, 1000);
            case 'DECIMAL':
                return (string) $this->faker->randomFloat(2, 0, 999.99);
            case 'BOOLEAN':
                return $this->faker->boolean() ? 'true' : 'false';
            case 'JSON':
                return json_encode([
                    'key1' => $this->faker->word(),
                    'key2' => $this->faker->numberBetween(1, 100),
                    'key3' => $this->faker->boolean()
                ]);
            case 'DATE':
                return $this->faker->date();
            case 'TIME':
                return $this->faker->time();
            case 'STRING':
            default:
                return $this->faker->words(3, true);
        }
    }

    /**
     * Estado para parámetros de validaciones
     */
    public function validaciones(): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => 'VALIDACIONES',
        ]);
    }

    /**
     * Estado para parámetros de reportes
     */
    public function reportes(): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => 'REPORTES',
        ]);
    }

    /**
     * Estado para parámetros generales
     */
    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => 'GENERAL',
        ]);
    }

    /**
     * Estado para parámetros no modificables
     */
    public function protegido(): static
    {
        return $this->state(fn (array $attributes) => [
            'modificable' => false,
        ]);
    }

    /**
     * Estado para parámetros booleanos
     */
    public function booleano(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'BOOLEAN',
            'valor' => $this->faker->boolean() ? 'true' : 'false',
            'valor_por_defecto' => 'false',
            'opciones' => json_encode(['true', 'false']),
        ]);
    }

    /**
     * Estado para parámetros enteros
     */
    public function entero(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'INTEGER',
            'valor' => (string) $this->faker->numberBetween(1, 100),
            'valor_por_defecto' => '10',
        ]);
    }

    /**
     * Estado para parámetros con opciones predefinidas
     */
    public function conOpciones(): static
    {
        $opciones = $this->faker->words(3);

        return $this->state(fn (array $attributes) => [
            'opciones' => json_encode($opciones),
            'valor' => $this->faker->randomElement($opciones),
            'valor_por_defecto' => $opciones[0],
        ]);
    }
}

// ============================================================================
// TESTS UNITARIOS
// ============================================================================

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Parametro;
use App\Models\User;
use App\Helpers\ParametroHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class ParametroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario para las pruebas
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function puede_crear_parametro_basico()
    {
        $parametro = Parametro::factory()->create([
            'clave' => 'test_parametro',
            'valor' => 'test_valor'
        ]);

        $this->assertDatabaseHas('parametros', [
            'clave' => 'test_parametro',
            'valor' => 'test_valor'
        ]);
    }

    /** @test */
    public function puede_obtener_valor_de_parametro()
    {
        Parametro::factory()->create([
            'clave' => 'test_get',
            'valor' => 'valor_esperado'
        ]);

        $valor = ParametroHelper::get('test_get');

        $this->assertEquals('valor_esperado', $valor);
    }

    /** @test */
    public function devuelve_valor_por_defecto_si_parametro_no_existe()
    {
        $valor = ParametroHelper::get('parametro_inexistente', 'valor_defecto');

        $this->assertEquals('valor_defecto', $valor);
    }

    /** @test */
    public function puede_establecer_valor_de_parametro()
    {
        $parametro = Parametro::factory()->create([
            'clave' => 'test_set',
            'valor' => 'valor_original',
            'modificable' => true
        ]);

        $resultado = ParametroHelper::set('test_set', 'nuevo_valor', $this->user->id);

        $this->assertTrue($resultado);
        $this->assertEquals('nuevo_valor', $parametro->fresh()->valor);
    }

    /** @test */
    public function no_puede_modificar_parametro_protegido()
    {
        $parametro = Parametro::factory()->create([
            'clave' => 'test_protegido',
            'valor' => 'valor_original',
            'modificable' => false
        ]);

        $this->expectException(\Exception::class);

        ParametroHelper::set('test_protegido', 'nuevo_valor', $this->user->id);
    }

    /** @test */
    public function valida_tipo_integer_correctamente()
    {
        $parametro = Parametro::factory()->entero()->create([
            'clave' => 'test_integer'
        ]);

        $this->assertTrue(ParametroHelper::isValid('test_integer', '123'));
        $this->assertFalse(ParametroHelper::isValid('test_integer', 'no_es_numero'));
    }

    /** @test */
    public function valida_tipo_boolean_correctamente()
    {
        $parametro = Parametro::factory()->booleano()->create([
            'clave' => 'test_boolean'
        ]);

        $this->assertTrue(ParametroHelper::isValid('test_boolean', 'true'));
        $this->assertTrue(ParametroHelper::isValid('test_boolean', 'false'));
        $this->assertFalse(ParametroHelper::isValid('test_boolean', 'maybe'));
    }

    /** @test */
    public function obtiene_valor_booleano_correctamente()
    {
        Parametro::factory()->create([
            'clave' => 'test_bool_true',
            'tipo' => 'BOOLEAN',
            'valor' => 'true'
        ]);

        Parametro::factory()->create([
            'clave' => 'test_bool_false',
            'tipo' => 'BOOLEAN',
            'valor' => 'false'
        ]);

        $this->assertTrue(ParametroHelper::getBool('test_bool_true'));
        $this->assertFalse(ParametroHelper::getBool('test_bool_false'));
    }

    /** @test */
    public function obtiene_parametros_por_categoria()
    {
        Parametro::factory()->count(3)->validaciones()->create();
        Parametro::factory()->count(2)->reportes()->create();

        $validaciones = ParametroHelper::getCategoria('VALIDACIONES');
        $reportes = ParametroHelper::getCategoria('REPORTES');

        $this->assertCount(3, $validaciones);
        $this->assertCount(2, $reportes);
    }

    /** @test */
    public function puede_restaurar_parametro_a_valor_defecto()
    {
        $parametro = Parametro::factory()->create([
            'clave' => 'test_restore',
            'valor' => 'valor_modificado',
            'valor_por_defecto' => 'valor_original',
            'modificable' => true
        ]);

        $resultado = ParametroHelper::restore('test_restore');

        $this->assertTrue($resultado);
        $this->assertEquals('valor_original', $parametro->fresh()->valor);
    }

    /** @test */
    public function cachea_valores_correctamente()
    {
        $parametro = Parametro::factory()->create([
            'clave' => 'test_cache',
            'valor' => 'valor_cacheado'
        ]);

        // Primera llamada - debe consultar BD
        $valor1 = ParametroHelper::get('test_cache');

        // Segunda llamada - debe usar caché
        $valor2 = ParametroHelper::get('test_cache');

        $this->assertEquals($valor1, $valor2);
        $this->assertEquals('valor_cacheado', $valor1);
    }

    /** @test */
    public function limpia_cache_al_actualizar_parametro()
    {
        $parametro = Parametro::factory()->create([
            'clave' => 'test_cache_clear',
            'valor' => 'valor_original',
            'modificable' => true
        ]);

        // Cachear valor
        $valorOriginal = ParametroHelper::get('test_cache_clear');

        // Actualizar valor
        ParametroHelper::set('test_cache_clear', 'valor_nuevo', $this->user->id);

        // Verificar que el caché se actualizó
        $valorNuevo = ParametroHelper::get('test_cache_clear');

        $this->assertEquals('valor_original', $valorOriginal);
        $this->assertEquals('valor_nuevo', $valorNuevo);
    }

    /** @test */
    public function obtiene_estadisticas_correctamente()
    {
        Parametro::factory()->count(5)->validaciones()->create();
        Parametro::factory()->count(3)->reportes()->create();
        Parametro::factory()->count(2)->protegido()->create();

        $stats = ParametroHelper::getStats();

        $this->assertEquals(10, $stats['total']);
        $this->assertEquals(8, $stats['modificables']); // 10 - 2 protegidos
        $this->assertArrayHasKey('VALIDACIONES', $stats['por_categoria']);
        $this->assertArrayHasKey('REPORTES', $stats['por_categoria']);
    }

    protected function tearDown(): void
    {
        // Limpiar caché después de cada test
        ParametroHelper::clearCache();
        parent::tearDown();
    }
}

// ============================================================================
// TESTS DE FEATURE (INTEGRACIÓN)
// ============================================================================

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ParametroControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function puede_ver_lista_de_parametros()
    {
        Parametro::factory()->count(5)->create();

        $response = $this->get(route('parametros.index'));

        $response->assertStatus(200);
        $response->assertViewIs('parametros.index');
        $response->assertViewHas('parametros');
    }

    /** @test */
    public function puede_crear_parametro()
    {
        $data = [
            'categoria' => 'TEST',
            'clave' => 'nuevo_parametro',
            'nombre' => 'Nuevo Parámetro',
            'descripcion' => 'Descripción del parámetro',
            'tipo' => 'STRING',
            'valor' => 'valor_test',
            'valor_por_defecto' => 'defecto_test',
            'modificable' => true,
            'visible_interfaz' => true,
            'orden_visualizacion' => 1
        ];

        $response = $this->post(route('parametros.store'), $data);

        $response->assertRedirect(route('parametros.index'));
        $this->assertDatabaseHas('parametros', [
            'clave' => 'nuevo_parametro',
            'valor' => 'valor_test'
        ]);
    }

    /** @test */
    public function valida_datos_al_crear_parametro()
    {
        $response = $this->post(route('parametros.store'), []);

        $response->assertSessionHasErrors(['categoria', 'clave', 'nombre', 'tipo', 'valor', 'valor_por_defecto']);
    }

    /** @test */
    public function puede_exportar_configuracion()
    {
        Parametro::factory()->count(3)->create();

        $response = $this->get(route('parametros.exportar'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');
    }

    /** @test */
    public function puede_importar_configuracion()
    {
        Storage::fake('local');

        $configuracion = [
            'TEST' => [
                'parametro_importado' => [
                    'nombre' => 'Parámetro Importado',
                    'descripcion' => 'Descripción',
                    'tipo' => 'STRING',
                    'valor_actual' => 'valor_importado',
                    'valor_por_defecto' => 'defecto',
                    'opciones' => null,
                    'modificable' => true,
                    'orden_visualizacion' => 1
                ]
            ]
        ];

        $archivo = UploadedFile::fake()->createWithContent(
            'parametros.json',
            json_encode($configuracion)
        );

        $response = $this->post(route('parametros.importar'), [
            'archivo' => $archivo
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('parametros', [
            'clave' => 'parametro_importado',
            'valor' => 'valor_importado'
        ]);
    }

    /** @test */
    public function puede_descargar_plantilla()
    {
        $response = $this->get(route('parametros.plantilla'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');
        $response->assertHeader('content-disposition', 'attachment; filename="plantilla_parametros.json"');
    }
}

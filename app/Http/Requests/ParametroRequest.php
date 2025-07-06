<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParametroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $parametroId = $this->route('parametro') ? $this->route('parametro')->id : null;

        return [
            'categoria' => [
                'required',
                'string',
                'max:50',
                'alpha_dash'
            ],
            'clave' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z_]+$/',
                Rule::unique('parametros', 'clave')->ignore($parametroId)
            ],
            'nombre' => [
                'required',
                'string',
                'max:200'
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'tipo' => [
                'required',
                Rule::in(['STRING', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'JSON', 'DATE', 'TIME'])
            ],
            'valor' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $this->validarValorSegunTipo($attribute, $value, $fail);
                }
            ],
            'valor_por_defecto' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $this->validarValorSegunTipo($attribute, $value, $fail, true);
                }
            ],
            'opciones' => [
                'nullable',
                'string'
            ],
            'modificable' => [
                'boolean'
            ],
            'visible_interfaz' => [
                'boolean'
            ],
            'orden_visualizacion' => [
                'integer',
                'min:0',
                'max:9999'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'categoria.required' => 'La categoría es obligatoria.',
            'categoria.alpha_dash' => 'La categoría solo puede contener letras, números, guiones y guiones bajos.',
            'clave.required' => 'La clave es obligatoria.',
            'clave.unique' => 'Ya existe un parámetro con esta clave.',
            'clave.regex' => 'La clave solo puede contener letras minúsculas y guiones bajos.',
            'nombre.required' => 'El nombre es obligatorio.',
            'tipo.required' => 'El tipo de dato es obligatorio.',
            'tipo.in' => 'El tipo de dato debe ser válido.',
            'valor.required' => 'El valor actual es obligatorio.',
            'valor_por_defecto.required' => 'El valor por defecto es obligatorio.',
        ];
    }

    private function validarValorSegunTipo($attribute, $value, $fail, $esValorDefecto = false)
    {
        $tipo = $this->input('tipo');
        $campo = $esValorDefecto ? 'valor por defecto' : 'valor actual';

        switch ($tipo) {
            case 'INTEGER':
                if (!is_numeric($value) || intval($value) != $value) {
                    $fail("El {$campo} debe ser un número entero válido.");
                }
                break;

            case 'DECIMAL':
                if (!is_numeric($value)) {
                    $fail("El {$campo} debe ser un número decimal válido.");
                }
                break;

            case 'BOOLEAN':
                $valoresValidos = ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'];
                if (!in_array(strtolower($value), $valoresValidos)) {
                    $fail("El {$campo} debe ser un valor booleano válido.");
                }
                break;

            case 'JSON':
                json_decode($value);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $fail("El {$campo} debe ser un JSON válido.");
                }
                break;

            case 'DATE':
                try {
                    \Carbon\Carbon::parse($value);
                } catch (\Exception $e) {
                    $fail("El {$campo} debe ser una fecha válida.");
                }
                break;

            case 'TIME':
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value)) {
                    $fail("El {$campo} debe ser una hora válida.");
                }
                break;
        }

        $opciones = $this->input('opciones');
        if ($opciones && !$esValorDefecto) {
            $opcionesArray = array_map('trim', explode(',', $opciones));
            if (!in_array($value, $opcionesArray)) {
                $fail("El {$campo} debe ser una de las opciones válidas.");
            }
        }
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('modificable')) {
            $this->merge(['modificable' => $this->boolean('modificable')]);
        }

        if ($this->has('visible_interfaz')) {
            $this->merge(['visible_interfaz' => $this->boolean('visible_interfaz')]);
        }

        if ($this->has('categoria')) {
            $this->merge(['categoria' => strtoupper(trim($this->input('categoria')))]);
        }

        if ($this->has('clave')) {
            $this->merge(['clave' => strtolower(trim($this->input('clave')))]);
        }

        if ($this->has('orden_visualizacion')) {
            $this->merge(['orden_visualizacion' => (int) $this->input('orden_visualizacion', 0)]);
        }
    }

    public function getValidatedData(): array
    {
        $data = $this->validated();

        if (isset($data['opciones']) && !empty($data['opciones'])) {
            $data['opciones'] = json_encode(array_map('trim', explode(',', $data['opciones'])));
        } else {
            $data['opciones'] = null;
        }

        $data['modificado_por'] = auth()->id();
        return $data;
    }
}

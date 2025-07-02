<?php

namespace App\Imports;

use App\Models\Conductor;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ConductoresImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        return Conductor::updateOrCreate(
            ['codigo' => $row['codigo']],
            [
                'nombre' => $row['nombre'],
                'email' => $row['email'],
                'telefono' => $row['telefono'],
                'origen' => $row['origen'],
                'estado' => $row['estado'] ?? 'DISPONIBLE',
                'disponibilidad_llegada' => $row['disponibilidad_llegada'],
                'ultima_hora_servicio' => $row['ultima_hora_servicio'] ? now()->parse($row['ultima_hora_servicio']) : null,
                'dias_acumulados' => $row['dias_acumulados'] ?? 0,
                'puntualidad' => $row['puntualidad'] ?? 95.0,
                'eficiencia' => $row['eficiencia'] ?? 95.0,
                'incidencias' => $row['incidencias'] ?? 0,
                'fecha_ingreso' => $row['fecha_ingreso'] ? now()->parse($row['fecha_ingreso']) : now(),
                'licencia' => $row['licencia'] ?? 'A-IIb',
            ]
        );
    }

    public function rules(): array
    {
        return [
            'codigo' => 'required|string',
            'nombre' => 'required|string',
            'email' => 'required|email',
            'origen' => 'required|in:LIMA,ICA,CHINCHA,PISCO,CAÑETE,NAZCA',
        ];
    }
}

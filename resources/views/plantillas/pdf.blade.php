<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Plantilla: {{ $plantilla->nombre }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        .header { text-align: center; margin-bottom: 20px; }
        .logo { font-size: 18px; font-weight: bold; color: #4e73df; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
        th { background-color: #f8f9fc; font-weight: bold; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">SIPAT - Sistema de Planificación</div>
        <h2>{{ $plantilla->nombre }}</h2>
        <p>Generado el: {{ now()->format('d/m/Y H:i') }}</p>
        @if($plantilla->descripcion)
            <p>{{ $plantilla->descripcion }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha Salida</th>
                <th>N° Salida</th>
                <th>Hora Salida</th>
                <th>Hora Llegada</th>
                <th>Código Bus</th>
                <th>Código Conductor</th>
                <th>Nombre Conductor</th>
                <th>Tipo Servicio</th>
                <th>Origen-Destino</th>
                <th>Origen Conductor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($turnos as $turno)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($turno->fecha_salida)->format('d/m/Y') }}</td>
                    <td>{{ $turno->numero_salida }}</td>
                    <td>{{ $turno->hora_salida }}</td>
                    <td>{{ $turno->hora_llegada }}</td>
                    <td>{{ $turno->codigo_bus }}</td>
                    <td>{{ $turno->codigo_conductor }}</td>
                    <td>{{ $turno->nombre_conductor }}</td>
                    <td>{{ $turno->tipo_servicio }}</td>
                    <td>{{ $turno->origen_destino }}</td>
                    <td>{{ $turno->origen_conductor }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Total de turnos: {{ $turnos->count() }}</p>
        <p>SIPAT - Sistema de Planificación y Administración de Transporte</p>
    </div>
</body>
</html>

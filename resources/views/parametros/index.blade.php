@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Parámetros Predictivos</h1>

    <a href="{{ route('parametros_predictivos.create') }}" class="btn btn-primary">
        Crear Nuevo Parámetro
    </a>

    <table class="table">
        <thead>
            <tr>
                <th>Clave</th>
                <th>Tipo</th>
                <th>Activo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($parametros as $parametro)
            <tr>
                <td>{{ $parametro->clave }}</td>
                <td>{{ $parametro->tipo_prediccion }}</td>
                <td>{{ $parametro->activo ? 'Sí' : 'No' }}</td>
                <td>
                    <a href="#" class="btn btn-sm btn-info">Ver</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

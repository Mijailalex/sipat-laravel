@extends('layouts.app')

@section('content')
<h1>Centro de Reportes</h1>

<div class="row">
    <div class="col-md-3">
        <div class="card text-center bg-primary text-white">
            <div class="card-body">
                <h4>{{ $metricas['conductores_total'] }}</h4>
                <small>Conductores</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-success text-white">
            <div class="card-body">
                <h4>{{ $metricas['conductores_activos'] }}</h4>
                <small>Activos</small>
            </div>
        </div>
    </div>
</div>
@endsection

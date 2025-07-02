@extends('layouts.app')

@section('title', 'Validaciones - SIPAT')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-check-circle text-success"></i>
        Sistema de Validaciones
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" onclick="ejecutarValidaciones()">
            <i class="fas fa-play"></i> Ejecutar Validaciones
        </button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center bg-primary text-white">
            <div class="card-body">
                <h4>{{ $metricas['total'] }}</h4>
                <small>Total</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-warning text-white">
            <div class="card-body">
                <h4>{{ $metricas['pendientes'] }}</h4>
                <small>Pendientes</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-danger text-white">
            <div class="card-body">
                <h4>{{ $metricas['criticas'] }}</h4>
                <small>Críticas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-success text-white">
            <div class="card-body">
                <h4>{{ $metricas['resueltas_hoy'] }}</h4>
                <small>Resueltas Hoy</small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($validaciones->count() > 0)
            @foreach($validaciones as $validacion)
                <div class="alert alert-{{ $validacion->severidad == 'CRITICA' ? 'danger' : 'warning' }}">
                    <strong>{{ $validacion->tipo }}</strong><br>
                    {{ $validacion->mensaje }}<br>
                    <small>Conductor: {{ $validacion->conductor->nombre ?? 'N/A' }}</small>
                </div>
            @endforeach
        @else
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5>No hay validaciones pendientes</h5>
                <button class="btn btn-primary" onclick="ejecutarValidaciones()">
                    <i class="fas fa-play"></i> Ejecutar Validaciones
                </button>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
function ejecutarValidaciones() {
    fetch('{{ route("validaciones.ejecutar") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        location.reload();
    });
}
</script>
@endsection

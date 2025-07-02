@extends('layouts.app')

@section('title', 'Notificaciones - SIPAT')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-bell"></i> Centro de Notificaciones
    </h1>
    <button class="btn btn-primary" onclick="marcarTodasLeidas()">
        <i class="fas fa-check-double"></i> Marcar todas como leídas
    </button>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            Tienes <strong>{{ $noLeidas }}</strong> notificaciones sin leer
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @forelse($notificaciones as $notificacion)
            <div class="alert alert-{{ $notificacion->severidad_color }} {{ is_null($notificacion->leida_en) ? 'border-left-4' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">{{ $notificacion->titulo }}</h6>
                        <p class="mb-1">{{ $notificacion->mensaje }}</p>
                        <small class="text-muted">
                            {{ $notificacion->created_at->diffForHumans() }}
                            @if($notificacion->leida_en)
                                - <i class="fas fa-check text-success"></i> Leída
                            @endif
                        </small>
                    </div>
                    @if(is_null($notificacion->leida_en))
                        <button class="btn btn-sm btn-outline-secondary" onclick="marcarLeida({{ $notificacion->id }})">
                            <i class="fas fa-check"></i>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <h5>No hay notificaciones</h5>
                <p class="text-muted">Cuando tengas nuevas notificaciones aparecerán aquí</p>
            </div>
        @endforelse

        {{ $notificaciones->links() }}
    </div>
</div>
@endsection

@section('scripts')
<script>
function marcarLeida(id) {
    fetch(`/notificaciones/${id}/marcar-leida`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function marcarTodasLeidas() {
    fetch('/notificaciones/marcar-todas-leidas', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}
</script>
@endsection

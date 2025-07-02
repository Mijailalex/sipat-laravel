@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Notificaciones de {{ $conductor->nombre }}</h1>

    @if($notificaciones->isEmpty())
        <div class="alert alert-info">
            No hay notificaciones
        </div>
    @else
        <div class="card">
            <div class="card-header">
                Notificaciones
                <button id="marcar-todas" class="btn btn-sm btn-secondary float-right">
                    Marcar todas como leídas
                </button>
            </div>
            <ul class="list-group list-group-flush">
                @foreach($notificaciones as $notificacion)
                    <li class="list-group-item {{ $notificacion->leida ? 'text-muted' : '' }}">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>{{ $notificacion->tipo }}</strong>
                                <p>{{ $notificacion->mensaje }}</p>
                            </div>
                            <small>{{ $notificacion->created_at->diffForHumans() }}</small>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{ $notificaciones->links() }}
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Marcar notificación individual
    document.querySelectorAll('.marcar-leida').forEach(button => {
        button.addEventListener('click', function() {
            const notificacionId = this.dataset.id;

            fetch(`/notificaciones/${notificacionId}/marcar-leida`, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.closest('.list-group-item').classList.add('text-muted');
                }
            });
        });
    });

    // Marcar todas las notificaciones
    document.getElementById('marcar-todas').addEventListener('click', function() {
        fetch(`/notificaciones/conductor/{{ $conductor->id }}/marcar-todas`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.list-group-item').forEach(item => {
                    item.classList.add('text-muted');
                });
            }
        });
    });
});
</script>
@endpush

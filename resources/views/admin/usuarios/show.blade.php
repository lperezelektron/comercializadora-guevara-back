@extends('layouts.admin')

@section('title', $usuario->name)
@section('header', 'Detalle del Usuario')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.usuarios.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver a usuarios
    </a>
    <div class="d-flex gap-2">
        {{-- Toggle status --}}
        <form method="POST" action="{{ route('admin.usuarios.toggle', $usuario) }}">
            @csrf @method('PATCH')
            <button type="submit"
                    class="btn btn-sm btn-outline-{{ $usuario->status === 'active' ? 'secondary' : 'success' }}"
                    {{ $usuario->id === auth()->id() ? 'disabled' : '' }}>
                <i class="bi bi-{{ $usuario->status === 'active' ? 'pause-circle' : 'play-circle' }} me-1"></i>
                {{ $usuario->status === 'active' ? 'Desactivar' : 'Activar' }}
            </button>
        </form>
        <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="btn btn-sm btn-warning">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
    </div>
</div>

<div class="row g-4">
    {{-- Datos principales --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center py-4">
                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold mx-auto mb-3"
                     style="width:72px;height:72px;font-size:1.6rem;">
                    {{ strtoupper(substr($usuario->name, 0, 2)) }}
                </div>
                <h5 class="fw-bold mb-1">{{ $usuario->name }}</h5>
                <p class="text-muted small mb-2">{{ $usuario->email }}</p>
                <span class="badge {{ $usuario->status === 'active' ? 'bg-success' : 'bg-danger' }}">
                    {{ $usuario->status === 'active' ? 'Activo' : 'Inactivo' }}
                </span>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Información</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Rol</dt>
                    <dd class="col-7">
                        @if($usuario->role)
                            <a href="{{ route('admin.roles.show', $usuario->role) }}"
                               class="badge bg-info-subtle text-info border border-info-subtle text-decoration-none">
                                {{ $usuario->role->name }}
                            </a>
                        @else
                            <span class="text-muted">Sin rol</span>
                        @endif
                    </dd>

                    <dt class="col-5 text-muted">Teléfono</dt>
                    <dd class="col-7">{{ $usuario->telefono ?? '—' }}</dd>

                    <dt class="col-5 text-muted">Dirección</dt>
                    <dd class="col-7">{{ $usuario->direccion ?? '—' }}</dd>

                    <dt class="col-5 text-muted">Registro</dt>
                    <dd class="col-7">{{ $usuario->created_at->format('d/m/Y') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Permisos del rol --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-key me-2 text-warning"></i>
                    Permisos heredados del rol
                    @if($usuario->role)
                        <span class="badge bg-secondary ms-1">{{ $usuario->role->permissions->count() }}</span>
                    @endif
                </h6>
            </div>
            <div class="card-body">
                @if(!$usuario->role || $usuario->role->permissions->isEmpty())
                    <p class="text-muted text-center py-3">
                        {{ $usuario->role ? 'El rol no tiene permisos asignados.' : 'El usuario no tiene rol asignado.' }}
                    </p>
                @else
                    @php
                        $agrupados = $usuario->role->permissions->groupBy(fn($p) => explode('.', $p->name)[0]);
                    @endphp
                    <div class="row g-3">
                        @foreach($agrupados as $modulo => $lista)
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <h6 class="text-capitalize fw-semibold mb-2 text-primary">
                                    <i class="bi bi-folder2-open me-1"></i>{{ $modulo }}
                                </h6>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($lista as $permiso)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"
                                              title="{{ $permiso->description }}">
                                            <i class="bi bi-check2 me-1"></i>{{ $permiso->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

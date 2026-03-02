@extends('layouts.admin')

@section('title', $role->name)
@section('header', 'Detalle del Rol')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.roles.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver a roles
    </a>
    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-warning btn-sm">
        <i class="bi bi-pencil me-1"></i>Editar rol
    </a>
</div>

<div class="row g-4">
    <!-- Info básica -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-shield me-2 text-primary"></i>Información</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted small">Nombre</dt>
                    <dd class="col-7 fw-semibold">{{ $role->name }}</dd>

                    <dt class="col-5 text-muted small">Descripción</dt>
                    <dd class="col-7">{{ $role->description ?? '—' }}</dd>

                    <dt class="col-5 text-muted small">Permisos</dt>
                    <dd class="col-7">
                        <span class="badge bg-primary">{{ $role->permissions->count() }}</span>
                    </dd>

                    <dt class="col-5 text-muted small">Usuarios</dt>
                    <dd class="col-7">
                        <span class="badge bg-secondary">{{ $role->users->count() }}</span>
                    </dd>
                </dl>
            </div>
        </div>

        <!-- Usuarios asignados -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2 text-secondary"></i>Usuarios con este rol</h6>
            </div>
            <div class="card-body p-0">
                @if($role->users->isEmpty())
                    <p class="text-muted text-center py-3 small">Sin usuarios asignados.</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($role->users as $usuario)
                        <li class="list-group-item d-flex align-items-center gap-2 py-2">
                            <i class="bi bi-person-circle text-muted"></i>
                            <div>
                                <div class="fw-semibold small">{{ $usuario->name }}</div>
                                <div class="text-muted" style="font-size:.8rem">{{ $usuario->email }}</div>
                            </div>
                            <span class="badge ms-auto {{ $usuario->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                {{ $usuario->status === 'active' ? 'Activo' : 'Inactivo' }}
                            </span>
                        </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <!-- Permisos -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-key me-2 text-warning"></i>Permisos asignados</h6>
            </div>

            @php
                $permisosAgrupados = $role->permissions->groupBy(fn($p) => explode('.', $p->name)[0]);
            @endphp

            <div class="card-body">
                @if($role->permissions->isEmpty())
                    <p class="text-muted text-center py-3">Este rol no tiene permisos asignados.</p>
                @else
                    <div class="row g-3">
                        @foreach($permisosAgrupados as $modulo => $lista)
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <h6 class="text-capitalize fw-semibold mb-2 text-primary">
                                    <i class="bi bi-folder2-open me-1"></i>{{ $modulo }}
                                </h6>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($lista as $permiso)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
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

            <!-- Formulario rápido para sincronizar permisos -->
            <div class="card-footer bg-white border-top">
                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-pencil-square me-1"></i>Gestionar permisos
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

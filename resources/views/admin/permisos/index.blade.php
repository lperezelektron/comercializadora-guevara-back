@extends('layouts.admin')

@section('title', 'Permisos')
@section('header', 'Gestión de Permisos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-1 fw-bold">Permisos del sistema</h5>
        <p class="text-muted small mb-0">
            {{ $agrupados->flatten()->count() }} permiso(s) en {{ $agrupados->count() }} módulo(s)
        </p>
    </div>
    <a href="{{ route('admin.permisos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo Permiso
    </a>
</div>

{{-- Filtros --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Buscar por nombre o descripción..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="modulo" class="form-select form-select-sm">
                    <option value="">Todos los módulos</option>
                    @foreach($modulos as $mod)
                        <option value="{{ $mod }}" {{ request('modulo') === $mod ? 'selected' : '' }}>
                            {{ ucfirst($mod) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
                @if(request()->hasAny(['search','modulo']))
                    <a href="{{ route('admin.permisos.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Permisos agrupados por módulo --}}
@if($agrupados->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="text-center py-5 text-muted">
            <i class="bi bi-key fs-1 d-block mb-2"></i>
            No se encontraron permisos.
            <a href="{{ route('admin.permisos.create') }}">Crear el primero</a>
        </div>
    </div>
@else
    @foreach($agrupados as $modulo => $lista)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold text-capitalize">
                <i class="bi bi-folder2-open me-2 text-primary"></i>{{ $modulo }}
                <span class="badge bg-primary-subtle text-primary ms-1">{{ $lista->count() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light" style="font-size:.85rem;">
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="text-center">Roles que lo usan</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lista->sortBy('name') as $permiso)
                    <tr>
                        <td>
                            <code class="text-primary fw-semibold">{{ $permiso->name }}</code>
                        </td>
                        <td class="text-muted small">{{ $permiso->description ?? '—' }}</td>
                        <td class="text-center">
                            <span class="badge {{ $permiso->roles_count > 0 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} rounded-pill">
                                {{ $permiso->roles_count }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.permisos.edit', $permiso) }}"
                                   class="btn btn-outline-warning" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                        data-bs-toggle="modal" data-bs-target="#modalEliminar"
                                        data-id="{{ $permiso->id }}"
                                        data-name="{{ $permiso->name }}"
                                        data-roles="{{ $permiso->roles_count }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
@endif

{{-- Modal Eliminar --}}
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle text-danger me-2"></i>Eliminar permiso
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar el permiso <code id="modalNombre" class="text-primary"></code>?</p>
                <div id="alertaRoles" class="alert alert-warning d-none">
                    <i class="bi bi-shield-exclamation me-2"></i>
                    Este permiso está asignado a uno o más roles y no puede eliminarse.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="formEliminar" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger" id="btnConfirmar">
                        <i class="bi bi-trash me-1"></i>Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.getElementById('modalEliminar').addEventListener('show.bs.modal', function (e) {
    const btn   = e.relatedTarget;
    const roles = parseInt(btn.dataset.roles);

    document.getElementById('modalNombre').textContent = btn.dataset.name;
    document.getElementById('formEliminar').action = `/admin/permisos/${btn.dataset.id}`;
    document.getElementById('alertaRoles').classList.toggle('d-none', roles === 0);
    document.getElementById('btnConfirmar').disabled = roles > 0;
});
</script>
@endsection

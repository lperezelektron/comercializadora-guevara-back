@extends('layouts.admin')

@section('title', 'Usuarios')
@section('header', 'Gestión de Usuarios')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-1 fw-bold">Usuarios del sistema</h5>
        <p class="text-muted small mb-0">{{ $usuarios->total() }} usuario(s) registrados</p>
    </div>
    <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Nuevo Usuario
    </a>
</div>

{{-- Filtros --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Buscar por nombre o email..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="role_id" class="form-select form-select-sm">
                    <option value="">Todos los roles</option>
                    @foreach($roles as $rol)
                        <option value="{{ $rol->id }}" {{ request('role_id') == $rol->id ? 'selected' : '' }}>
                            {{ $rol->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos los estados</option>
                    <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Activo</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
                @if(request()->hasAny(['search','role_id','status']))
                    <a href="{{ route('admin.usuarios.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Tabla --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        @if($usuarios->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-person-x fs-1 d-block mb-2"></i>
                No se encontraron usuarios.
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Teléfono</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usuarios as $usuario)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold"
                                     style="width:36px;height:36px;font-size:.85rem;">
                                    {{ strtoupper(substr($usuario->name, 0, 2)) }}
                                </div>
                                <span class="fw-semibold">{{ $usuario->name }}</span>
                            </div>
                        </td>
                        <td class="text-muted small">{{ $usuario->email }}</td>
                        <td>
                            @if($usuario->role)
                                <span class="badge bg-info-subtle text-info border border-info-subtle">
                                    {{ $usuario->role->name }}
                                </span>
                            @else
                                <span class="text-muted small">Sin rol</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $usuario->telefono ?? '—' }}</td>
                        <td class="text-center">
                            <span class="badge {{ $usuario->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                {{ $usuario->status === 'active' ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.usuarios.show', $usuario) }}"
                                   class="btn btn-outline-info" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.usuarios.edit', $usuario) }}"
                                   class="btn btn-outline-warning" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                {{-- Toggle status --}}
                                <form method="POST" action="{{ route('admin.usuarios.toggle', $usuario) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                            class="btn btn-outline-{{ $usuario->status === 'active' ? 'secondary' : 'success' }}"
                                            title="{{ $usuario->status === 'active' ? 'Desactivar' : 'Activar' }}"
                                            {{ $usuario->id === auth()->id() ? 'disabled' : '' }}>
                                        <i class="bi bi-{{ $usuario->status === 'active' ? 'pause-circle' : 'play-circle' }}"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                        data-bs-toggle="modal" data-bs-target="#modalEliminar"
                                        data-id="{{ $usuario->id }}"
                                        data-name="{{ $usuario->name }}"
                                        data-self="{{ $usuario->id === auth()->id() ? '1' : '0' }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if($usuarios->hasPages())
        <div class="d-flex justify-content-center py-3 border-top">
            {{ $usuarios->links('pagination::bootstrap-5') }}
        </div>
        @endif
        @endif
    </div>
</div>

{{-- Modal Eliminar --}}
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Eliminar usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar a <strong id="modalNombre"></strong>?</p>
                <p class="text-muted small">Si el usuario tiene ventas o compras registradas, será desactivado en lugar de eliminado.</p>
                <div id="alertaSelf" class="alert alert-warning d-none">
                    <i class="bi bi-person-exclamation me-2"></i>No puedes eliminar tu propia cuenta.
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
    const btn  = e.relatedTarget;
    const isSelf = btn.dataset.self === '1';

    document.getElementById('modalNombre').textContent = btn.dataset.name;
    document.getElementById('formEliminar').action = `/admin/usuarios/${btn.dataset.id}`;
    document.getElementById('alertaSelf').classList.toggle('d-none', !isSelf);
    document.getElementById('btnConfirmar').disabled = isSelf;
});
</script>
@endsection

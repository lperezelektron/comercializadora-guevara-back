@extends('layouts.admin')

@section('title', 'Roles')
@section('header', 'Gestión de Roles')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-1 fw-bold">Roles del sistema</h5>
        <p class="text-muted small mb-0">Administra los roles y sus permisos</p>
    </div>
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo Rol
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        @if($roles->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-shield-x fs-1 d-block mb-2"></i>
                No hay roles registrados.
                <a href="{{ route('admin.roles.create') }}">Crear el primero</a>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="text-center">Permisos</th>
                        <th class="text-center">Usuarios</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $role->name }}</span>
                        </td>
                        <td class="text-muted small">{{ $role->description ?? '—' }}</td>
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary rounded-pill">
                                {{ $role->permissions_count }}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill">
                                {{ $role->users_count }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.roles.show', $role) }}"
                                   class="btn btn-outline-info" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.roles.edit', $role) }}"
                                   class="btn btn-outline-warning" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger"
                                        title="Eliminar"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEliminar"
                                        data-id="{{ $role->id }}"
                                        data-name="{{ $role->name }}"
                                        data-usuarios="{{ $role->users_count }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

<!-- Modal Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Eliminar rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar el rol <strong id="modalRolNombre"></strong>?</p>
                <div id="alertaUsuarios" class="alert alert-warning d-none">
                    <i class="bi bi-people me-2"></i>Este rol tiene usuarios asignados y no puede eliminarse.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="formEliminar" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" id="btnConfirmarEliminar">
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
document.getElementById('modalEliminar').addEventListener('show.bs.modal', function (event) {
    const btn       = event.relatedTarget;
    const id        = btn.dataset.id;
    const name      = btn.dataset.name;
    const usuarios  = parseInt(btn.dataset.usuarios);

    document.getElementById('modalRolNombre').textContent = name;

    const form      = document.getElementById('formEliminar');
    const alerta    = document.getElementById('alertaUsuarios');
    const btnConf   = document.getElementById('btnConfirmarEliminar');

    form.action = `/admin/roles/${id}`;

    if (usuarios > 0) {
        alerta.classList.remove('d-none');
        btnConf.disabled = true;
    } else {
        alerta.classList.add('d-none');
        btnConf.disabled = false;
    }
});
</script>
@endsection

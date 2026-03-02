@extends('layouts.admin')

@section('title', 'Editar Rol')
@section('header', 'Editar Rol')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.roles.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver a roles
    </a>
</div>

<form method="POST" action="{{ route('admin.roles.update', $role) }}">
    @csrf
    @method('PUT')

    <div class="row g-4">
        <!-- Datos del rol -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-shield me-2 text-warning"></i>Datos del rol</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $role->name) }}" autofocus>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Descripción</label>
                        <textarea id="description" name="description" rows="3"
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Descripción del rol...">{{ old('description', $role->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="card-footer bg-white border-top d-flex gap-2">
                    <button type="submit" class="btn btn-warning flex-fill">
                        <i class="bi bi-check-lg me-1"></i>Actualizar
                    </button>
                    <a href="{{ route('admin.roles.show', $role) }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>
                </div>
            </div>
        </div>

        <!-- Permisos -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-key me-2 text-warning"></i>Permisos</h6>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="toggleAll">
                        <label class="form-check-label small text-muted" for="toggleAll">Todos</label>
                    </div>
                </div>
                <div class="card-body">
                    @if($permisos->isEmpty())
                        <p class="text-muted text-center py-3">No hay permisos registrados en el sistema.</p>
                    @else
                        <div class="row g-3">
                            @foreach($permisos as $modulo => $lista)
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <h6 class="text-capitalize fw-semibold mb-2 text-primary">
                                        <i class="bi bi-folder2-open me-1"></i>{{ $modulo }}
                                    </h6>
                                    @foreach($lista as $permiso)
                                    <div class="form-check">
                                        <input class="form-check-input perm-check" type="checkbox"
                                               name="permisos[]"
                                               id="perm_{{ $permiso->id }}"
                                               value="{{ $permiso->id }}"
                                               {{ in_array($permiso->id, old('permisos', $permisosAsignados)) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="perm_{{ $permiso->id }}">
                                            {{ $permiso->name }}
                                            @if($permiso->description)
                                                <span class="text-muted">— {{ $permiso->description }}</span>
                                            @endif
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
const toggleAll = document.getElementById('toggleAll');
const checks    = document.querySelectorAll('.perm-check');

// Estado inicial del toggle
toggleAll.checked = [...checks].every(cb => cb.checked);
toggleAll.addEventListener('change', function () {
    checks.forEach(cb => cb.checked = this.checked);
});
</script>
@endsection

@extends('layouts.admin')

@section('title', 'Editar Permiso')
@section('header', 'Editar Permiso')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.permisos.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver a permisos
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-pencil me-2 text-warning"></i>
                    Editando: <code class="text-primary">{{ $permission->name }}</code>
                </h6>
            </div>

            <form method="POST" action="{{ route('admin.permisos.update', $permission) }}">
                @csrf @method('PUT')
                <div class="card-body">

                    {{-- Nombre compuesto --}}
                    <div class="mb-1">
                        <label class="form-label fw-semibold">
                            Nombre del permiso <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-5">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-folder2"></i></span>
                                    <input type="text" name="modulo" id="modulo"
                                           list="modulosList"
                                           class="form-control @error('modulo') is-invalid @enderror"
                                           value="{{ old('modulo', $modulo) }}"
                                           autocomplete="off">
                                    <datalist id="modulosList">
                                        @foreach($modulos as $mod)
                                            <option value="{{ $mod }}">
                                        @endforeach
                                    </datalist>
                                    @error('modulo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-1 d-flex align-items-center justify-content-center text-muted fw-bold">
                                .
                            </div>
                            <div class="col-6">
                                <input type="text" name="accion" id="accion"
                                       class="form-control @error('accion') is-invalid @enderror"
                                       value="{{ old('accion', $accion) }}"
                                       autocomplete="off">
                                @error('accion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Preview --}}
                    <div class="mb-3 mt-1">
                        <small class="text-muted">
                            Vista previa: <code id="preview" class="text-primary fw-semibold">{{ $permission->name }}</code>
                        </small>
                        @if($permission->roles()->exists())
                            <div class="alert alert-warning mt-2 py-2 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Este permiso está en uso por
                                <strong>{{ $permission->roles()->count() }} rol(es)</strong>.
                                Cambiar el nombre puede afectar la autorización del sistema.
                            </div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Descripción</label>
                        <input type="text" id="description" name="description"
                               class="form-control @error('description') is-invalid @enderror"
                               value="{{ old('description', $permission->description) }}"
                               placeholder="Breve descripción del permiso">
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="card-footer bg-white border-top d-flex gap-2">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-1"></i>Actualizar
                    </button>
                    <a href="{{ route('admin.permisos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function actualizarPreview() {
    const modulo = document.getElementById('modulo').value.trim().toLowerCase().replace(/[^a-z_]/g, '');
    const accion = document.getElementById('accion').value.trim().toLowerCase().replace(/[^a-z_]/g, '');
    const preview = document.getElementById('preview');

    preview.textContent = modulo || accion ? `${modulo || '…'}.${accion || '…'}` : '—';
}

document.getElementById('modulo').addEventListener('input', actualizarPreview);
document.getElementById('accion').addEventListener('input', actualizarPreview);
</script>
@endsection

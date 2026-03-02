@extends('layouts.admin')

@section('title', 'Editar Usuario')
@section('header', 'Editar Usuario')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.usuarios.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver a usuarios
    </a>
</div>

<div class="row g-4 justify-content-center">
    {{-- Formulario principal --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-pencil me-2 text-warning"></i>Datos del usuario</h6>
            </div>

            <form method="POST" action="{{ route('admin.usuarios.update', $usuario) }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $usuario->name) }}" autofocus>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">Correo electrónico <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $usuario->email) }}">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="role_id" class="form-label fw-semibold">Rol <span class="text-danger">*</span></label>
                            <select id="role_id" name="role_id"
                                    class="form-select @error('role_id') is-invalid @enderror">
                                <option value="">— Seleccionar rol —</option>
                                @foreach($roles as $rol)
                                    <option value="{{ $rol->id }}"
                                        {{ old('role_id', $usuario->role_id) == $rol->id ? 'selected' : '' }}>
                                        {{ $rol->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('role_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="status" class="form-label fw-semibold">Estado</label>
                            <select id="status" name="status" class="form-select"
                                    {{ $usuario->id === auth()->id() ? 'disabled' : '' }}>
                                <option value="active"   {{ old('status', $usuario->status) === 'active'   ? 'selected' : '' }}>Activo</option>
                                <option value="inactive" {{ old('status', $usuario->status) === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                            </select>
                            @if($usuario->id === auth()->id())
                                <input type="hidden" name="status" value="{{ $usuario->status }}">
                                <div class="form-text">No puedes cambiar tu propio estado.</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                            <input type="text" id="telefono" name="telefono"
                                   class="form-control @error('telefono') is-invalid @enderror"
                                   value="{{ old('telefono', $usuario->telefono) }}">
                            @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label for="direccion" class="form-label fw-semibold">Dirección</label>
                            <textarea id="direccion" name="direccion" rows="2"
                                      class="form-control @error('direccion') is-invalid @enderror">{{ old('direccion', $usuario->direccion) }}</textarea>
                            @error('direccion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white border-top d-flex gap-2">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-1"></i>Actualizar
                    </button>
                    <a href="{{ route('admin.usuarios.show', $usuario) }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Panel lateral: reset de contraseña --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm border-start border-4 border-warning">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-key me-2 text-warning"></i>Resetear contraseña</h6>
            </div>

            <form method="POST" action="{{ route('admin.usuarios.reset-password', $usuario) }}">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold">Nueva contraseña</label>
                        <div class="input-group">
                            <input type="password" id="new_password" name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   autocomplete="new-password" placeholder="Mín. 8 caracteres">
                            <button class="btn btn-outline-secondary" type="button" id="toggleReset">
                                <i class="bi bi-eye"></i>
                            </button>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-1">
                        <label for="new_password_confirmation" class="form-label fw-semibold">Confirmar</label>
                        <input type="password" id="new_password_confirmation" name="password_confirmation"
                               class="form-control" autocomplete="new-password">
                    </div>
                    <p class="text-muted small mt-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>Los tokens activos del usuario serán revocados.
                    </p>
                </div>
                <div class="card-footer bg-white border-top">
                    <button type="submit" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-key me-1"></i>Cambiar contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function togglePasswordVisibility(btnId, inputId) {
    document.getElementById(btnId).addEventListener('click', function () {
        const input = document.getElementById(inputId);
        const icon  = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });
}
togglePasswordVisibility('toggleReset', 'new_password');
</script>
@endsection

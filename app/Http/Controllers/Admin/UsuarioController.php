<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('role')->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            );
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $usuarios = $query->paginate(15)->withQueryString();
        $roles    = Role::orderBy('name')->get();

        return view('admin.usuarios.index', compact('usuarios', 'roles'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();

        return view('admin.usuarios.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            'role_id'    => ['required', 'exists:roles,id'],
            'telefono'   => ['nullable', 'string', 'max:20'],
            'direccion'  => ['nullable', 'string', 'max:255'],
            'status'     => ['in:active,inactive'],
            'almacen_id' => ['nullable', 'exists:almacenes,id'],
        ]);

        $usuario = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'role_id'    => $data['role_id'],
            'telefono'   => $data['telefono'] ?? null,
            'direccion'  => $data['direccion'] ?? null,
            'status'     => $data['status'] ?? 'active',
            'almacen_id' => $data['almacen_id'] ?? null,
        ]);

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario \"{$usuario->name}\" creado correctamente.");
    }

    public function show(User $usuario)
    {
        $usuario->load('role.permissions');

        return view('admin.usuarios.show', compact('usuario'));
    }

    public function edit(User $usuario)
    {
        $roles = Role::orderBy('name')->get();

        return view('admin.usuarios.edit', compact('usuario', 'roles'));
    }

    public function update(Request $request, User $usuario)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', "unique:users,email,{$usuario->id}"],
            'role_id'    => ['required', 'exists:roles,id'],
            'telefono'   => ['nullable', 'string', 'max:20'],
            'direccion'  => ['nullable', 'string', 'max:255'],
            'status'     => ['in:active,inactive'],
            'almacen_id' => ['nullable', 'exists:almacenes,id'],
        ]);

        $usuario->update([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'role_id'    => $data['role_id'],
            'telefono'   => $data['telefono'] ?? null,
            'direccion'  => $data['direccion'] ?? null,
            'status'     => $data['status'] ?? $usuario->status,
            'almacen_id' => $data['almacen_id'] ?? $usuario->almacen_id,
        ]);

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario \"{$usuario->name}\" actualizado correctamente.");
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($usuario->ventas()->exists() || $usuario->compras()->exists()) {
            $usuario->update(['status' => 'inactive']);
            $usuario->tokens()->delete();

            return redirect()->route('admin.usuarios.index')
                ->with('success', "Usuario \"{$usuario->name}\" desactivado (tiene movimientos registrados).");
        }

        $nombre = $usuario->name;
        $usuario->tokens()->delete();
        $usuario->delete();

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario \"{$nombre}\" eliminado correctamente.");
    }

    public function toggleStatus(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $nuevo = $usuario->status === 'active' ? 'inactive' : 'active';
        $usuario->update(['status' => $nuevo]);

        if ($nuevo === 'inactive') {
            $usuario->tokens()->delete();
        }

        $etiqueta = $nuevo === 'active' ? 'activado' : 'desactivado';

        return back()->with('success', "Usuario \"{$usuario->name}\" {$etiqueta} correctamente.");
    }

    public function resetPassword(Request $request, User $usuario)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $usuario->update(['password' => Hash::make($data['password'])]);
        $usuario->tokens()->delete();

        return back()->with('success', "Contraseña de \"{$usuario->name}\" actualizada. El usuario deberá iniciar sesión de nuevo.");
    }
}

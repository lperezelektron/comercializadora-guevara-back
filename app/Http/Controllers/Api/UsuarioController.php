<?php
// ============================================================
// app/Http/Controllers/Api/UsuarioController.php
// ============================================================
// Modelo User:
//   - campos: name, email, password, role_id, telefono, direccion, status
//   - relaciones: role() belongsTo, compras(), ventas(), etc.
//   - métodos: hasRole(), hasPermission(), isActive()
//   - status: 'active' | 'inactive'
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    /**
     * Listar usuarios
     */
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('usuarios.ver')) {
            return response()->json(['message' => 'Sin permisos para ver usuarios.'], 403);
        }

        $query = User::with('role')->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status); // 'active' | 'inactive'
        }

        $usuarios = $request->filled('per_page')
            ? $query->paginate($request->per_page)
            : $query->get();

        return response()->json($usuarios);
    }

    /**
     * Crear usuario
     */
    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('usuarios.crear')) {
            return response()->json(['message' => 'Sin permisos para crear usuarios.'], 403);
        }

        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:8',
            'role_id'    => 'required|exists:roles,id',
            'telefono'   => 'nullable|string|max:20',
            'direccion'  => 'nullable|string|max:255',
            'status'     => 'in:active,inactive',
            'almacen_id' => 'nullable|exists:almacenes,id',
        ]);

        $usuario = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role_id'    => $request->role_id,
            'telefono'   => $request->telefono,
            'direccion'  => $request->direccion,
            'status'     => $request->status ?? 'active',
            'almacen_id' => $request->almacen_id,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'usuario' => $usuario->load('role'),
        ], 201);
    }

    /**
     * Ver usuario con su rol y permisos
     */
    public function show(User $usuario)
    {
        if (!auth()->user()->hasPermission('usuarios.ver')) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $usuario->load('role.permissions');

        return response()->json([
            'usuario'  => $usuario,
            'permisos' => $usuario->role?->permissions->pluck('name') ?? [],
        ]);
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, User $usuario)
    {
        if (!auth()->user()->hasPermission('usuarios.editar')) {
            return response()->json(['message' => 'Sin permisos para editar usuarios.'], 403);
        }

        $request->validate([
            'name'       => 'sometimes|required|string|max:255',
            'email'      => 'sometimes|required|email|unique:users,email,' . $usuario->id,
            'role_id'    => 'sometimes|required|exists:roles,id',
            'telefono'   => 'nullable|string|max:20',
            'direccion'  => 'nullable|string|max:255',
            'status'     => 'in:active,inactive',
            'almacen_id' => 'nullable|exists:almacenes,id',
        ]);

        $usuario->update($request->only('name', 'email', 'role_id', 'telefono', 'direccion', 'status', 'almacen_id'));

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'usuario' => $usuario->load('role'),
        ]);
    }

    /**
     * Resetear contraseña (solo admin)
     */
    public function resetPassword(Request $request, User $usuario)
    {
        if (!auth()->user()->hasPermission('usuarios.editar')) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $usuario->update(['password' => Hash::make($request->password)]);

        // Revocar tokens para forzar nuevo login
        $usuario->tokens()->delete();

        return response()->json(['message' => 'Contraseña reseteada. El usuario debe iniciar sesión de nuevo.']);
    }

    /**
     * Activar / desactivar usuario
     */
    public function toggleStatus(User $usuario)
    {
        if (!auth()->user()->hasPermission('usuarios.editar')) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        if ($usuario->id === auth()->id()) {
            return response()->json(['message' => 'No puedes desactivar tu propia cuenta.'], 422);
        }

        $nuevoStatus = $usuario->status === 'active' ? 'inactive' : 'active';
        $usuario->update(['status' => $nuevoStatus]);

        if ($nuevoStatus === 'inactive') {
            $usuario->tokens()->delete();
        }

        return response()->json([
            'message' => 'Estado actualizado.',
            'status'  => $nuevoStatus,
        ]);
    }

    /**
     * Eliminar usuario
     */
    public function destroy(User $usuario)
    {
        if (!auth()->user()->hasPermission('usuarios.eliminar')) {
            return response()->json(['message' => 'Sin permisos para eliminar usuarios.'], 403);
        }

        if ($usuario->id === auth()->id()) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 422);
        }

        if ($usuario->ventas()->exists() || $usuario->compras()->exists()) {
            // En lugar de eliminar, desactivar
            $usuario->update(['status' => 'inactive']);
            $usuario->tokens()->delete();
            return response()->json(['message' => 'Usuario desactivado (tiene movimientos registrados).']);
        }

        $usuario->tokens()->delete();
        $usuario->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }
}
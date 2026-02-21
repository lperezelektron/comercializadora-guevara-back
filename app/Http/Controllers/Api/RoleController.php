<?php
// ============================================================
// app/Http/Controllers/Api/RoleController.php
// ============================================================
// Modelo Role:
//   - campos: name, description
//   - tabla pivote: role_permission
//   - relaciones: users() hasMany, permissions() belongsToMany
//   - métodos: givePermissionTo(), removePermissionTo(), hasPermission()
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Listar roles con permisos y conteo de usuarios
     */
    public function index()
    {
        if (!auth()->user()->hasPermission('roles.ver')) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json($roles);
    }

    /**
     * Crear rol
     */
    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('roles.crear')) {
            return response()->json(['message' => 'Sin permisos para crear roles.'], 403);
        }

        $request->validate([
            'name'        => 'required|string|max:50|unique:roles,name',
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        if ($request->filled('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'message' => 'Rol creado correctamente.',
            'role'    => $role->load('permissions'),
        ], 201);
    }

    /**
     * Ver rol con permisos y usuarios
     */
    public function show(Role $role)
    {
        if (!auth()->user()->hasPermission('roles.ver')) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        return response()->json(
            $role->load('permissions', 'users')
        );
    }

    /**
     * Actualizar rol y sus permisos
     */
    public function update(Request $request, Role $role)
    {
        if (!auth()->user()->hasPermission('roles.editar')) {
            return response()->json(['message' => 'Sin permisos para editar roles.'], 403);
        }

        $request->validate([
            'name'          => 'sometimes|required|string|max:50|unique:roles,name,' . $role->id,
            'description'   => 'nullable|string|max:255',
            'permissions'   => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update($request->only('name', 'description'));

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'message' => 'Rol actualizado correctamente.',
            'role'    => $role->load('permissions'),
        ]);
    }

    /**
     * Eliminar rol
     */
    public function destroy(Role $role)
    {
        if (!auth()->user()->hasPermission('roles.eliminar')) {
            return response()->json(['message' => 'Sin permisos para eliminar roles.'], 403);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el rol tiene usuarios asignados.',
            ], 422);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json(['message' => 'Rol eliminado correctamente.']);
    }

    /**
     * Asignar permisos individuales a un rol
     */
    public function asignarPermiso(Request $request, Role $role)
    {
        if (!auth()->user()->hasPermission('roles.editar')) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);

        $role->givePermissionTo($request->permission);

        return response()->json([
            'message'    => "Permiso '{$request->permission}' asignado a '{$role->name}'.",
            'permisos'   => $role->permissions()->pluck('name'),
        ]);
    }

    /**
     * Revocar permiso individual de un rol
     */
    public function revocarPermiso(Request $request, Role $role)
    {
        if (!auth()->user()->hasPermission('roles.editar')) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);

        $role->removePermissionTo($request->permission);

        return response()->json([
            'message'  => "Permiso '{$request->permission}' revocado de '{$role->name}'.",
            'permisos' => $role->permissions()->pluck('name'),
        ]);
    }
}
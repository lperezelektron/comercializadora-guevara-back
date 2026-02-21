<?php
// ============================================================
// app/Http/Controllers/Api/PermissionController.php
// ============================================================
// Modelo Permission:
//   - campos: name, description
//   - tabla pivote: role_permission
//   - relaciones: roles() belongsToMany
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Listar todos los permisos agrupados por módulo
     * El módulo se infiere del prefijo del name (ej: "ventas.crear" → módulo "ventas")
     */
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('roles.ver')) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $permisos = Permission::orderBy('name')->get();

        if ($request->boolean('agrupar')) {
            // Agrupa por el prefijo antes del punto: "ventas.crear" → "ventas"
            $agrupados = $permisos->groupBy(function ($p) {
                return explode('.', $p->name)[0] ?? 'general';
            });

            return response()->json($agrupados);
        }

        return response()->json($permisos);
    }

    /**
     * Crear permiso
     */
    public function store(Request $request)
    {
        // Solo superadmin puede crear permisos
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Solo el administrador puede crear permisos.'], 403);
        }

        $request->validate([
            'name'        => 'required|string|max:100|unique:permissions,name',
            'description' => 'nullable|string|max:255',
        ]);

        $permission = Permission::create([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return response()->json([
            'message'    => 'Permiso creado correctamente.',
            'permission' => $permission,
        ], 201);
    }

    /**
     * Ver permiso con los roles que lo tienen
     */
    public function show(Permission $permission)
    {
        if (!auth()->user()->hasPermission('roles.ver')) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        return response()->json(
            $permission->load('roles')
        );
    }

    /**
     * Actualizar permiso
     */
    public function update(Request $request, Permission $permission)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Solo el administrador puede editar permisos.'], 403);
        }

        $request->validate([
            'name'        => 'sometimes|required|string|max:100|unique:permissions,name,' . $permission->id,
            'description' => 'nullable|string|max:255',
        ]);

        $permission->update($request->only('name', 'description'));

        return response()->json([
            'message'    => 'Permiso actualizado.',
            'permission' => $permission,
        ]);
    }

    /**
     * Eliminar permiso
     */
    public function destroy(Permission $permission)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Solo el administrador puede eliminar permisos.'], 403);
        }

        if ($permission->roles()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el permiso está asignado a uno o más roles.',
            ], 422);
        }

        $permission->delete();

        return response()->json(['message' => 'Permiso eliminado correctamente.']);
    }
}

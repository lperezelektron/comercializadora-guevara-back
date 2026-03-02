<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $query = Permission::withCount('roles')->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
            );
        }

        if ($request->filled('modulo')) {
            $query->where('name', 'like', "{$request->modulo}.%");
        }

        $permisos = $query->get();

        // Módulos disponibles para el filtro
        $modulos = Permission::orderBy('name')
            ->pluck('name')
            ->map(fn ($n) => explode('.', $n)[0])
            ->unique()
            ->values();

        $agrupados = $permisos->groupBy(fn ($p) => explode('.', $p->name)[0]);

        return view('admin.permisos.index', compact('agrupados', 'modulos'));
    }

    public function create()
    {
        $modulos = Permission::orderBy('name')
            ->pluck('name')
            ->map(fn ($n) => explode('.', $n)[0])
            ->unique()
            ->values();

        return view('admin.permisos.create', compact('modulos'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'modulo'      => ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/'],
            'accion'      => ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $name = "{$data['modulo']}.{$data['accion']}";

        if (Permission::where('name', $name)->exists()) {
            return back()->withErrors(['accion' => "El permiso \"{$name}\" ya existe."])->withInput();
        }

        Permission::create(['name' => $name, 'description' => $data['description'] ?? null]);

        return redirect()->route('admin.permisos.index')
            ->with('success', "Permiso \"{$name}\" creado correctamente.");
    }

    public function edit(Permission $permission)
    {
        $modulos = Permission::orderBy('name')
            ->pluck('name')
            ->map(fn ($n) => explode('.', $n)[0])
            ->unique()
            ->values();

        [$modulo, $accion] = array_pad(explode('.', $permission->name, 2), 2, '');

        return view('admin.permisos.edit', compact('permission', 'modulos', 'modulo', 'accion'));
    }

    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate([
            'modulo'      => ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/'],
            'accion'      => ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $name = "{$data['modulo']}.{$data['accion']}";

        if (Permission::where('name', $name)->where('id', '!=', $permission->id)->exists()) {
            return back()->withErrors(['accion' => "El permiso \"{$name}\" ya existe."])->withInput();
        }

        $permission->update(['name' => $name, 'description' => $data['description'] ?? null]);

        return redirect()->route('admin.permisos.index')
            ->with('success', "Permiso actualizado a \"{$name}\" correctamente.");
    }

    public function destroy(Permission $permission)
    {
        if ($permission->roles()->exists()) {
            return back()->with('error', "No se puede eliminar \"{$permission->name}\": está asignado a uno o más roles.");
        }

        $nombre = $permission->name;
        $permission->delete();

        return redirect()->route('admin.permisos.index')
            ->with('success', "Permiso \"{$nombre}\" eliminado correctamente.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['permissions', 'users'])->orderBy('name')->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $permisos = Permission::orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]);

        return view('admin.roles.create', compact('permisos'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'permisos'    => ['nullable', 'array'],
            'permisos.*'  => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if (!empty($data['permisos'])) {
            $role->permissions()->sync($data['permisos']);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', "Rol \"{$role->name}\" creado correctamente.");
    }

    public function show(Role $role)
    {
        $role->load(['permissions', 'users']);

        return view('admin.roles.show', compact('role'));
    }

    public function edit(Role $role)
    {
        $role->load('permissions');
        $permisos        = Permission::orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]);
        $permisosAsignados = $role->permissions->pluck('id')->toArray();

        return view('admin.roles.edit', compact('role', 'permisos', 'permisosAsignados'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', "unique:roles,name,{$role->id}"],
            'description' => ['nullable', 'string', 'max:255'],
            'permisos'    => ['nullable', 'array'],
            'permisos.*'  => ['exists:permissions,id'],
        ]);

        $role->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permisos'] ?? []);

        return redirect()->route('admin.roles.index')
            ->with('success', "Rol \"{$role->name}\" actualizado correctamente.");
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return back()->with('error', "No se puede eliminar el rol \"{$role->name}\" porque tiene usuarios asignados.");
        }

        $nombre = $role->name;
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', "Rol \"{$nombre}\" eliminado correctamente.");
    }

    public function syncPermisos(Request $request, Role $role)
    {
        $data = $request->validate([
            'permisos'   => ['nullable', 'array'],
            'permisos.*' => ['exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permisos'] ?? []);

        return redirect()->route('admin.roles.show', $role)
            ->with('success', 'Permisos actualizados correctamente.');
    }
}

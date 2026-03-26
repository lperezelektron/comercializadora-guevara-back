<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use Illuminate\Http\Request;

class EmpleadoController extends Controller
{
    public function index(Request $request)
    {
        $query = Empleado::orderBy('nombre');

        if ($request->filled('search')) {
            $query->where('nombre', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        return response()->json(
            $request->filled('per_page')
                ? $query->paginate($request->per_page)
                : $query->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'activo' => 'boolean',
        ]);

        $empleado = Empleado::create([
            'nombre' => $request->nombre,
            'activo' => $request->boolean('activo', true),
        ]);

        return response()->json([
            'message'  => 'Empleado creado correctamente.',
            'empleado' => $empleado,
        ], 201);
    }

    public function show(Empleado $empleado)
    {
        return response()->json(['empleado' => $empleado]);
    }

    public function update(Request $request, Empleado $empleado)
    {
        $request->validate([
            'nombre' => 'sometimes|required|string|max:100',
            'activo' => 'boolean',
        ]);

        $empleado->update($request->only('nombre', 'activo'));

        return response()->json([
            'message'  => 'Empleado actualizado.',
            'empleado' => $empleado,
        ]);
    }

    public function destroy(Empleado $empleado)
    {
        $empleado->delete();

        return response()->json(['message' => 'Empleado eliminado.']);
    }
}

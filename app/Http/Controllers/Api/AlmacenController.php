<?php
// ============================================================
// app/Http/Controllers/Api/AlmacenController.php
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use Illuminate\Http\Request;

class AlmacenController extends Controller
{
    public function index(Request $request)
    {
        $query = Almacen::orderBy('descripcion');

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'descripcion' => 'required|string|max:100|unique:almacenes,descripcion',
            'direccion'   => 'required|string|max:255',
            'ciudad'      => 'nullable|string|max:50',
            'telefono'    => 'nullable|string|max:20',
            'activo'      => 'boolean',
        ]);

        $almacen = Almacen::create($request->only('descripcion', 'direccion', 'ciudad', 'telefono', 'activo'));

        return response()->json([
            'message' => 'Almacén creado correctamente.',
            'almacen' => $almacen,
        ], 201);
    }

    public function show(Almacen $almacen)
    {
        $almacen->load('inventarios.articulo.categoria');

        return response()->json([
            'almacen'         => $almacen,
            'valor_inventario'=> $almacen->valorInventario(),
        ]);
    }

    public function update(Request $request, Almacen $almacen)
    {
        $request->validate([
            'descripcion' => 'sometimes|required|string|max:100|unique:almacenes,descripcion,' . $almacen->id,
            'direccion'   => 'sometimes|required|string|max:255',
            'ciudad'      => 'nullable|string|max:50',
            'telefono'    => 'nullable|string|max:20',
            'activo'      => 'boolean',
        ]);

        $almacen->update($request->only('descripcion', 'direccion', 'ciudad', 'telefono', 'activo'));

        return response()->json([
            'message' => 'Almacén actualizado.',
            'almacen' => $almacen,
        ]);
    }

    public function destroy(Almacen $almacen)
    {
        if ($almacen->inventarios()->where('existencia', '>', 0)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene existencias registradas.',
            ], 422);
        }

        $almacen->inventarios()->delete();
        $almacen->delete();

        return response()->json(['message' => 'Almacén eliminado.']);
    }

    /**
     * Inventario completo del almacén
     */
    public function inventario(Request $request, Almacen $almacen)
    {
        $query = $almacen->inventarios()->with('articulo.categoria');

        if ($request->boolean('solo_con_stock')) {
            $query->where('existencia', '>', 0);
        }

        return response()->json($query->orderBy('articulo_id')->get());
    }
}
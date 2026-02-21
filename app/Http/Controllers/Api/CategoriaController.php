<?php
// ============================================================
// app/Http/Controllers/Api/CategoriaController.php
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index(Request $request)
    {
        $query = Categoria::withCount('articulos')->orderBy('descripcion');

        if ($request->filled('search')) {
            $query->where('descripcion', 'like', '%' . $request->search . '%');
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
            'descripcion' => 'required|string|max:50|unique:categorias,descripcion',
            'activo'      => 'boolean',
        ]);

        $categoria = Categoria::create([
            'descripcion' => $request->descripcion,
            'activo'      => $request->boolean('activo', true),
        ]);

        return response()->json([
            'message'   => 'Categoría creada correctamente.',
            'categoria' => $categoria,
        ], 201);
    }

    public function show(Categoria $categoria)
    {
        return response()->json($categoria->load('articulos'));
    }

    public function update(Request $request, Categoria $categoria)
    {
        $request->validate([
            'descripcion' => 'sometimes|required|string|max:50|unique:categorias,descripcion,' . $categoria->id,
            'activo'      => 'boolean',
        ]);

        $categoria->update($request->only('descripcion', 'activo'));

        return response()->json([
            'message'   => 'Categoría actualizada.',
            'categoria' => $categoria,
        ]);
    }

    public function destroy(Categoria $categoria)
    {
        if ($categoria->articulos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene artículos asignados.',
            ], 422);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }
}

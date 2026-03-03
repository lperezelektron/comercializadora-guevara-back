<?php
// ============================================================
// app/Http/Controllers/Api/ArticuloController.php
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Articulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticuloController extends Controller
{
    public function index(Request $request)
    {
        $query = Articulo::with('categoria')
            ->withSum('inventarios', 'existencia')
            ->orderBy('nombre');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('nombre_corto', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
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
            'nombre'       => 'required|string|max:50',
            'nombre_corto' => 'required|string|max:50',
            'unidad'       => 'required|string|max:5',
            'categoria_id' => 'required|exists:categorias,id',
            'activo'       => 'boolean',
            'imagen'       => 'nullable|image|max:2048',
        ]);

        $imagenPath = null;
        if ($request->hasFile('imagen')) {
            $ext        = $request->file('imagen')->getClientOriginalExtension();
            $imagenPath = $request->file('imagen')
                ->storeAs('articulos', Str::uuid() . '.' . $ext, 's3');
        }

        $articulo = Articulo::create([
            'nombre'       => $request->nombre,
            'nombre_corto' => $request->nombre_corto,
            'unidad'       => $request->unidad,
            'categoria_id' => $request->categoria_id,
            'activo'       => $request->boolean('activo', true),
            'imagen'       => $imagenPath,
        ]);

        return response()->json([
            'message'  => 'Artículo creado correctamente.',
            'articulo' => $articulo->load('categoria'),
        ], 201);
    }

    public function show(Articulo $articulo)
    {
        $articulo->load([
            'categoria',
            'inventarios.almacen',
        ]);

        return response()->json([
            'articulo'    => $articulo,
            'stock_total' => $articulo->stockTotal(),
        ]);
    }

    public function update(Request $request, Articulo $articulo)
    {
        $request->validate([
            'nombre'       => 'sometimes|required|string|max:50',
            'nombre_corto' => 'sometimes|required|string|max:50',
            'unidad'       => 'sometimes|required|string|max:5',
            'categoria_id' => 'sometimes|required|exists:categorias,id',
            'activo'       => 'boolean',
            'imagen'       => 'nullable|image|max:2048',
        ]);

        $data = $request->only('nombre', 'nombre_corto', 'unidad', 'categoria_id', 'activo');

        if ($request->hasFile('imagen')) {
            // Eliminar imagen anterior
            if ($articulo->imagen) {
                Storage::disk('s3')->delete($articulo->imagen);
            }
            $ext          = $request->file('imagen')->getClientOriginalExtension();
            $data['imagen'] = $request->file('imagen')
                ->storeAs('articulos', Str::uuid() . '.' . $ext, 's3');
        }

        $articulo->update($data);

        return response()->json([
            'message'  => 'Artículo actualizado.',
            'articulo' => $articulo->load('categoria'),
        ]);
    }

    public function destroy(Articulo $articulo)
    {
        if ($articulo->comprasDetalle()->exists() || $articulo->ventasDetalle()->exists()) {
            $articulo->update(['activo' => false]);
            return response()->json(['message' => 'Artículo desactivado (tiene movimientos registrados).']);
        }

        if ($articulo->imagen) {
            Storage::disk('s3')->delete($articulo->imagen);
        }

        $articulo->inventarios()->delete();
        $articulo->delete();

        return response()->json(['message' => 'Artículo eliminado.']);
    }

    public function stock(Articulo $articulo)
    {
        $inventarios = $articulo->inventarios()->with('almacen')->get();

        return response()->json([
            'articulo'    => $articulo->only('id', 'nombre', 'nombre_corto', 'unidad'),
            'inventarios' => $inventarios,
            'stock_total' => $inventarios->sum('existencia'),
        ]);
    }
}
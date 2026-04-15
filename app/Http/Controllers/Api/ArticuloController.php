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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ArticuloController extends Controller
{
    public function index(Request $request)
    {
        $query = Articulo::with('categoria')
            ->withSum('inventarios', 'existencia')
            ->orderBy('orden')
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
            'orden'        => 'integer|min:0',
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
            'orden'        => $request->input('orden', 0),
        ]);

        if ($articulo->orden === 0) {
            $articulo->update(['orden' => $articulo->id]);
        }

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
            'orden'        => 'integer|min:0',
        ]);

        $data = $request->only('nombre', 'nombre_corto', 'unidad', 'categoria_id', 'activo', 'orden');

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

    /**
     * Artículos con existencia > 0, paginados.
     * GET /articulos/con-existencia
     *   ?almacen_id=1        (requerido)
     *   &search=mango        (opcional, busca en nombre y nombre_corto)
     *   &categoria_id=2      (opcional)
     *   &per_page=50         (opcional, default 50)
     *   &page=1              (opcional, default 1)
     */
    public function conExistencia(Request $request)
    {
        $request->validate([
            'almacen_id' => 'required|exists:almacenes,id',
        ]);

        $perPage = (int) $request->get('per_page', 50);

        $query = Articulo::whereHas('inventarios', function ($q) use ($request) {
            $q->where('existencia', '>', 0)
              ->where('almacen_id', $request->almacen_id);
        })
        ->with(['categoria'])
        ->orderBy('orden')
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

        return response()->json($query->paginate($perPage));
    }

    /**
     * Actualiza el campo `orden` de múltiples artículos en una sola llamada.
     * POST /articulos/reordenar
     * Body: { "orden": [{"id": 1, "orden": 0}, {"id": 2, "orden": 1}, ...] }
     */
    public function reordenar(Request $request)
    {
        $request->validate([
            'orden'          => 'required|array|min:1',
            'orden.*.id'     => 'required|exists:articulos,id',
            'orden.*.orden'  => 'required|integer|min:0',
        ]);

        foreach ($request->orden as $item) {
            Articulo::where('id', $item['id'])->update(['orden' => $item['orden']]);
        }

        return response()->json(['message' => 'Orden actualizado correctamente.']);
    }

    public function importar(Request $request)
    {
        $request->validate([
            'articulos' => 'required|array|min:1',
            'articulos.*.nombre' => 'required|string|max:50',
            'articulos.*.nombre_corto' => 'required|string|max:50',
            'articulos.*.unidad' => 'required|string|max:5',
            'articulos.*.categoria_id' => 'required|exists:categorias,id',
        ]);

        $insertados = [];
        $errores = [];

        DB::beginTransaction();

        try {
            foreach ($request->articulos as $index => $item) {
                $fila = $index + 1;

                $validator = Validator::make($item, [
                    'nombre'       => 'required|string|max:50',
                    'nombre_corto' => 'required|string|max:50',
                    'unidad'       => 'required|string|max:5',
                    'categoria_id' => 'required|exists:categorias,id',
                ]);

                if ($validator->fails()) {
                    $errores[] = [
                        'fila' => $fila,
                        'nombre' => $item['nombre'] ?? null,
                        'errores' => $validator->errors()->all(),
                    ];
                    continue;
                }

                // Evitar duplicados exactos por nombre + categoria
                $existe = Articulo::where('nombre', $item['nombre'])
                    ->where('categoria_id', $item['categoria_id'])
                    ->exists();

                if ($existe) {
                    $errores[] = [
                        'fila' => $fila,
                        'nombre' => $item['nombre'],
                        'errores' => ['Ya existe un artículo con ese nombre en la categoría seleccionada.'],
                    ];
                    continue;
                }

                $articulo = Articulo::create([
                    'nombre'       => trim($item['nombre']),
                    'nombre_corto' => trim($item['nombre_corto']),
                    'unidad'       => trim($item['unidad']),
                    'categoria_id' => $item['categoria_id'],
                    'activo'       => true,
                    'imagen'       => null,
                    'orden'        => 0,
                ]);

                if ($articulo->orden === 0) {
                    $articulo->update(['orden' => $articulo->id]);
                }

                $insertados[] = $articulo->load('categoria');
            }

            DB::commit();

            return response()->json([
                'message' => 'Importación completada.',
                'insertados' => count($insertados),
                'errores' => $errores,
                'articulos' => $insertados,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Ocurrió un error durante la importación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventario;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    /**
     * Listado de inventario con filtros opcionales.
     * GET /inventario?almacen_id=&articulo_id=&search=
     */
    public function index(Request $request)
    {
        $query = Inventario::with(['almacen', 'articulo.categoria'])
            ->orderBy('articulo_id');

        if ($request->filled('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->filled('articulo_id')) {
            $query->where('articulo_id', $request->articulo_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('articulo', function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('nombre_corto', 'like', "%{$search}%");
            });
        }

        $inventarios = $request->filled('per_page')
            ? $query->paginate($request->per_page)
            : $query->get();

        return response()->json($inventarios);
    }

    /**
     * Detalle de un registro de inventario.
     * GET /inventario/{inventario}
     */
    public function show(Inventario $inventario)
    {
        $inventario->load(['almacen', 'articulo.categoria']);

        return response()->json([
            'inventario'      => $inventario,
            'margen_ganancia' => round($inventario->margenGanancia(), 2),
            'valor_total'     => round($inventario->valorTotal(), 2),
        ]);
    }

    /**
     * Actualizar precios de un registro de inventario.
     * PATCH /inventario/{inventario}/precios
     */
    public function updatePrecios(Request $request, Inventario $inventario)
    {
        $data = $request->validate([
            'precio'     => 'sometimes|required|numeric|min:0',
            'precio_min' => 'sometimes|required|numeric|min:0',
            'costo'      => 'sometimes|required|numeric|min:0',
        ]);

        if (isset($data['precio_min'], $data['precio']) && $data['precio_min'] > $data['precio']) {
            return response()->json([
                'message' => 'El precio mínimo no puede ser mayor al precio de venta.',
            ], 422);
        }

        // Validar con valores actuales cuando solo se envía uno de los dos
        $precioFinal    = $data['precio']     ?? $inventario->precio;
        $precioMinFinal = $data['precio_min'] ?? $inventario->precio_min;

        if ($precioMinFinal > $precioFinal) {
            return response()->json([
                'message' => 'El precio mínimo no puede ser mayor al precio de venta.',
            ], 422);
        }

        $inventario->update($data);

        return response()->json([
            'message'         => 'Precios actualizados correctamente.',
            'inventario'      => $inventario->fresh(['almacen', 'articulo']),
            'margen_ganancia' => round($inventario->fresh()->margenGanancia(), 2),
        ]);
    }

    /**
     * Actualización masiva de precios por almacén o artículo.
     * POST /inventario/precios-masivo
     *
     * Body: { "ids": [1,2,3], "precio": 10.5 }  — o cualquier combinación de campos.
     */
    public function updatePreciosMasivo(Request $request)
    {
        $request->validate([
            'ids'        => 'required|array|min:1',
            'ids.*'      => 'integer|exists:inventario,id',
            'precio'     => 'sometimes|required|numeric|min:0',
            'precio_min' => 'sometimes|required|numeric|min:0',
            'costo'      => 'sometimes|required|numeric|min:0',
        ]);

        $data = $request->only(['precio', 'precio_min', 'costo']);

        if (empty($data)) {
            return response()->json(['message' => 'Debe enviar al menos un campo a actualizar (precio, precio_min, costo).'], 422);
        }

        $actualizados = Inventario::whereIn('id', $request->ids)->update($data);

        return response()->json([
            'message'      => "Se actualizaron {$actualizados} registro(s) de inventario.",
            'actualizados' => $actualizados,
        ]);
    }
}

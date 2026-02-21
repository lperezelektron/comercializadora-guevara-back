<?php
// ============================================================
// app/Http/Controllers/Api/KardexController.php
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kardex;
use App\Models\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KardexController extends Controller
{
    /**
     * Consultar kardex de un lote (Inventario.id)
     */
    public function porLote(Request $request)
    {
        $request->validate([
            'lote_id' => 'required|exists:inventario,id',
        ]);

        $inventario = Inventario::with('articulo', 'almacen')->find($request->lote_id);

        $kardex = Kardex::porLote($request->lote_id)
            ->porFecha($request->fecha_inicio ?? '2000-01-01', $request->fecha_fin ?? now()->toDateString())
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        return response()->json([
            'lote'        => $inventario,
            'movimientos' => $kardex,
            'entradas'    => $kardex->where('tipo', 'Entrada')->sum('cantidad'),
            'salidas'     => $kardex->where('tipo', 'Salida')->sum('cantidad'),
        ]);
    }

    /**
     * Consultar kardex de todos los lotes de un artículo
     */
    public function porArticulo(Request $request)
    {
        $request->validate([
            'articulo_id' => 'required|exists:articulos,id',
            'almacen_id'  => 'nullable|exists:almacenes,id',
            'fecha_inicio'=> 'date',
            'fecha_fin'   => 'date',
        ]);

        $loteIds = Inventario::where('articulo_id', $request->articulo_id)
            ->when($request->filled('almacen_id'), fn($q) => $q->where('almacen_id', $request->almacen_id))
            ->pluck('id');

        $kardex = Kardex::whereIn('lote_id', $loteIds)
            ->porFecha(
                $request->fecha_inicio ?? now()->startOfMonth()->toDateString(),
                $request->fecha_fin   ?? now()->toDateString()
            )
            ->with([])  // lote → Inventario via CompraDetalle si necesitas el número de lote string
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        return response()->json([
            'articulo_id' => $request->articulo_id,
            'movimientos' => $kardex,
            'entradas'    => $kardex->where('tipo', 'Entrada')->sum('cantidad'),
            'salidas'     => $kardex->where('tipo', 'Salida')->sum('cantidad'),
        ]);
    }

    /**
     * Ajuste manual de inventario
     */
    public function ajuste(Request $request)
    {
        $request->validate([
            'lote_id'   => 'required|exists:inventario,id',
            'tipo'      => 'required|in:Entrada,Salida',
            'cantidad'  => 'required|numeric|min:0.001',
            'motivo'    => 'required|string|max:255',
            'fecha'     => 'date',
        ]);

        DB::beginTransaction();

        try {
            $inventario = Inventario::findOrFail($request->lote_id);

            if ($request->tipo === 'Salida') {
                $inventario->decreaseStock($request->cantidad);
            } else {
                $inventario->increaseStock($request->cantidad);
            }

            Kardex::create([
                'lote_id'   => $inventario->id,
                'fecha'     => $request->fecha ?? now()->toDateString(),
                'movimiento'=> 'Ajuste',
                'tipo'      => $request->tipo,
                'documento' => 'AJUSTE',
                'cte_prv'   => auth()->id(),
                'cantidad'  => $request->cantidad,
                'empaque'   => 0,
                'costo'     => $inventario->costo,
                'precio'    => $inventario->precio,
            ]);

            DB::commit();

            return response()->json([
                'message'          => 'Ajuste registrado correctamente.',
                'existencia_actual'=> $inventario->fresh()->existencia,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}

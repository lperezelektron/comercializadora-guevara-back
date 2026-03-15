<?php
// ============================================================
// app/Http/Controllers/Api/CajaController.php
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\CorteCaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    /**
     * Movimientos del día (o sin cerrar)
     */
    public function index(Request $request)
    {
        $query = Caja::with('user')->orderBy('fecha', 'desc')->orderBy('id', 'desc');

        $query->sinCerrar();

        if ($request->filled('almacen_id')) {
            $query->porAlmacen($request->almacen_id);
        }

        if ($request->filled('fecha')) {
            $query->delDia($request->fecha);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $movimientos = $query->get();

        return response()->json([
            'movimientos' => $movimientos,
            'saldo_actual'=> Caja::saldoActual($request->almacen_id),
            'totales'     => [
                'entradas' => $movimientos->where('tipo', 'entrada')->sum('cantidad'),
                'salidas'  => $movimientos->where('tipo', 'salida')->sum('cantidad'),
            ],
        ]);
    }

    /**
     * Registrar movimiento manual (gastos, depósitos, etc.)
     */
    public function movimiento(Request $request)
    {
        $request->validate([
            'tipo'        => 'required|in:Entrada,Salida',
            'cantidad'    => 'required|numeric|min:0.01',
            'referencia'  => 'required|string|max:255',
            'fecha'       => 'date',
            'almacen_id'  => 'nullable|exists:almacenes,id',
        ]);

        $tipo   = strtolower($request->tipo);
        $metodo = $tipo === 'entrada' ? 'entrada' : 'salida';

        $movimiento = Caja::{$metodo}($request->cantidad, $request->referencia, $request->almacen_id);

        if ($request->filled('fecha')) {
            $movimiento->update(['fecha' => $request->fecha]);
        }

        return response()->json([
            'message'     => 'Movimiento registrado.',
            'movimiento'  => $movimiento,
            'saldo_actual'=> Caja::saldoActual($request->almacen_id),
        ], 201);
    }

    /**
     * Realizar corte de caja
     * Cierra todos los movimientos sin corte y genera el registro de CorteCaja
     */
    public function corte(Request $request)
    {
        $request->validate([
            'almacen_id' => 'nullable|exists:almacenes,id',
        ]);

        $query = Caja::sinCerrar();
        if ($request->filled('almacen_id')) {
            $query->porAlmacen($request->almacen_id);
        }

        if ($query->doesntExist()) {
            return response()->json(['message' => 'No hay movimientos pendientes de corte.'], 422);
        }

        DB::beginTransaction();

        try {
            $corte = CorteCaja::create([
                'fecha'      => now()->toDateString(),
                'importe'    => 0, // se calcula en cerrar()
                'user_id'    => auth()->id(),
                'almacen_id' => $request->almacen_id,
            ]);

            // Asocia movimientos y calcula el importe
            $corte->cerrar();

            DB::commit();

            return response()->json([
                'message'  => 'Corte realizado correctamente.',
                'corte'    => $corte->load('movimientos'),
                'importe'  => $corte->importe,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al realizar el corte.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Historial de cortes
     */
    public function cortes(Request $request)
    {
        $query = CorteCaja::with(['user', 'almacen'])->orderBy('fecha', 'desc');

        if ($request->filled('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->filled('mes') && $request->filled('anio')) {
            $query->delMes($request->mes, $request->anio);
        }

        return response()->json(
            $request->filled('per_page')
                ? $query->paginate($request->per_page)
                : $query->get()
        );
    }

    /**
     * Detalle de un corte con sus movimientos
     */
    public function showCorte(CorteCaja $corteCaja)
    {
        return response()->json(
            $corteCaja->load(['user', 'movimientos.user'])
        );
    }
}

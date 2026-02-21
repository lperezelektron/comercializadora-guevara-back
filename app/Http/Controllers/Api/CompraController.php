<?php
// app/Http/Controllers/Api/CompraController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\CtaXPagar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $query = Compra::with(['proveedor', 'user'])
            ->orderBy('fecha', 'desc');

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha', '<=', $request->fecha_fin);
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
     * Registrar compra
     *
     * Flujo:
     * 1. Crear Compra
     * 2. Por cada detalle:
     *    a. Generar número de lote (string)
     *    b. Crear/actualizar registro en Inventario (almacen + articulo + variedad)
     *    c. Crear CompraDetalle con lote=string y referencia al Inventario.id
     *    d. Registrar entrada en Kardex usando Inventario.id como lote_id
     * 3. Si es crédito, crear CtaXPagar
     */
    public function store(Request $request)
    {
        $request->validate([
            'fecha'                          => 'required|date',
            'referencia'                     => 'nullable|string|max:255',
            'proveedor_id'                   => 'required|exists:proveedores,id',
            'almacen_id'                     => 'required|exists:almacenes,id',
            'subtotal'                       => 'required|numeric|min:0',
            'impuestos'                      => 'numeric|min:0',
            'total'                          => 'required|numeric|min:0',
            'credito'                        => 'boolean',
            'dias_credito'                   => 'required_if:credito,true|integer|min:1',
            'f_pago_id'                      => 'required_unless:credito,true|exists:forma_pago,id',
            'detalles'                       => 'required|array|min:1',
            'detalles.*.articulo_id'         => 'required|exists:articulos,id',
            'detalles.*.variedad'            => 'required|string|max:50',
            'detalles.*.cantidad'            => 'required|numeric|min:0.001',
            'detalles.*.empaque'             => 'numeric|min:0',
            'detalles.*.costo'               => 'required|numeric|min:0',
            'detalles.*.impuestos'           => 'numeric|min:0',
            'detalles.*.precio'              => 'required|numeric|min:0',
            'detalles.*.precio_min'          => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // 1. Crear la compra
            $referencia = $request->referencia ?? Compra::generarReferencia();

            $compra = Compra::create([
                'fecha'        => $request->fecha,
                'referencia'   => $referencia,
                'proveedor_id' => $request->proveedor_id,
                'user_id'      => auth()->id(),
                'subtotal'     => $request->subtotal,
                'impuestos'    => $request->impuestos ?? 0,
                'total'        => $request->total,
            ]);

            foreach ($request->detalles as $det) {
                // 2a. Generar número de lote único (string)
                $numeroLote = CompraDetalle::generarLote();

                // 2b. Crear o actualizar registro en Inventario
                //     Clave única: almacen_id + articulo_id + variedad
                $inventario = Inventario::firstOrNew([
                    'almacen_id'  => $request->almacen_id,
                    'articulo_id' => $det['articulo_id'],
                    'variedad'    => $det['variedad'],
                ]);

                $inventario->existencia += $det['cantidad'];
                $inventario->costo      = $det['costo'];        // actualiza costo
                $inventario->precio     = $det['precio'];       // actualiza precio venta
                $inventario->precioMin  = $det['precio_min'];
                $inventario->empaque    = $det['empaque'] ?? 0;
                $inventario->save();

                // 2c. Crear CompraDetalle
                //     lote = número de lote string (trazabilidad)
                //     Se guarda también inventario_id implícitamente via Inventario.id
                CompraDetalle::create([
                    'compra_id'   => $compra->id,
                    'lote'        => $numeroLote,
                    'articulo_id' => $det['articulo_id'],
                    'variedad'    => $det['variedad'],
                    'cantidad'    => $det['cantidad'],
                    'empaque'     => $det['empaque'] ?? 0,
                    'costo'       => $det['costo'],
                    'impuestos'   => $det['impuestos'] ?? 0,
                ]);

                // 2d. Registrar entrada en Kardex
                //     lote_id = Inventario.id (trazabilidad de partida)
                Kardex::registrarEntrada([
                    'lote_id'      => $inventario->id,
                    'fecha'        => $request->fecha,
                    'movimiento'   => 'Compra',
                    'documento'    => $compra->referencia,
                    'proveedor_id' => $request->proveedor_id,
                    'cantidad'     => $det['cantidad'],
                    'empaque'      => $det['empaque'] ?? 0,
                    'costo'        => $det['costo'],
                    'precio'       => $det['precio'],
                ]);
            }

            // 3. Crear CxP si es crédito
            if ($request->boolean('credito')) {
                $vencimiento = \Carbon\Carbon::parse($request->fecha)
                    ->addDays($request->dias_credito)
                    ->toDateString();

                CtaXPagar::create([
                    'compra_id'    => $compra->id,
                    'proveedor_id' => $request->proveedor_id,
                    'fecha'        => $request->fecha,
                    'vencimiento'  => $vencimiento,
                    'importe'      => $request->total,
                    'saldo'        => $request->total,
                ]);
            } else {
                // Registrar salida de caja (pago de contado)
                \App\Models\Caja::salida(
                    $request->total,
                    "Compra #{$compra->id} - {$compra->referencia}"
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Compra registrada correctamente.',
                'compra'  => $compra->load(['detalles.articulo', 'proveedor']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar la compra.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Compra $compra)
    {
        return response()->json(
            $compra->load(['proveedor', 'user', 'detalles.articulo', 'ctaPorPagar.detalles.formaPago'])
        );
    }

    /**
     * Cancelar compra — revierte existencias y kardex
     */
    public function cancelar(Request $request, Compra $compra)
    {
        if ($compra->estatus === 'cancelada') {
            return response()->json(['message' => 'La compra ya está cancelada.'], 422);
        }

        // Verificar que no haya ventas sobre estos lotes
        $lotesVendidos = $compra->detalles()
            ->whereHas('ventasDetalle')
            ->exists();

        if ($lotesVendidos) {
            return response()->json([
                'message' => 'No se puede cancelar: existen ventas registradas contra esta compra.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($compra->detalles as $det) {
                // Revertir existencia en Inventario
                // Buscar el inventario por almacén + artículo + variedad
                // (necesitamos el almacen_id de la compra — agregar al modelo si no existe)
                $inventario = Inventario::join('compras_detalle', function($join) use ($det) {
                    $join->on('inventario.articulo_id', '=', 'compras_detalle.articulo_id')
                         ->where('compras_detalle.lote', '=', $det->lote);
                })->select('inventario.*')->first();

                if ($inventario) {
                    $inventario->existencia -= $det->cantidad;
                    $inventario->existencia = max(0, $inventario->existencia);
                    $inventario->save();

                    // Registrar salida en Kardex (reversa)
                    Kardex::registrarSalida([
                        'lote_id'    => $inventario->id,
                        'fecha'      => now()->toDateString(),
                        'movimiento' => 'Cancelación Compra',
                        'documento'  => $compra->referencia,
                        'cliente_id' => $compra->proveedor_id,
                        'cantidad'   => $det->cantidad,
                        'empaque'    => $det->empaque,
                        'costo'      => $det->costo,
                        'precio'     => 0,
                    ]);
                }
            }

            // Cancelar CxP si existe y no tiene abonos
            if ($compra->ctaPorPagar && $compra->ctaPorPagar->saldo == $compra->ctaPorPagar->importe) {
                $compra->ctaPorPagar->delete();
            }

            $compra->update(['estatus' => 'cancelada']);

            DB::commit();

            return response()->json(['message' => 'Compra cancelada correctamente.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al cancelar la compra.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
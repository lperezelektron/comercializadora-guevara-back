<?php
// app/Http/Controllers/Api/VentaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\CtaXCobrar;
use App\Models\Caja;
use App\Services\TicketEscPos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $query = Venta::with(['cliente', 'almacen', 'user', 'formaPago'])
            ->orderBy('fecha', 'desc');

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->filled('almacen_id')) {
            $query->porAlmacen($request->almacen_id);
        }

        if ($request->filled('credito')) {
            $request->boolean('credito') ? $query->credito() : $query->contado();
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
     * Registrar venta
     *
     * Flujo:
     * 1. Validar stock disponible por lote (Inventario.id)
     * 2. Crear Venta
     * 3. Por cada detalle:
     *    a. Decrementar existencia en Inventario (usando lote_id = Inventario.id)
     *    b. Crear VentaDetalle con lote_id = Inventario.id
     *    c. Registrar salida en Kardex con lote_id = Inventario.id
     * 4. Si crédito → crear CtaXCobrar
     * 5. Si contado → registrar entrada en Caja
     */
    public function store(Request $request)
    {
        
        $request->validate([
            'fecha'                      => 'required|date',
            'cliente_id'                 => 'required|exists:clientes,id',
            'almacen_id'                 => 'required|exists:almacenes,id',
            'credito'                    => 'boolean',
            'f_pago_id'                  => 'required_unless:credito,true|nullable|exists:forma_pago,id',
            'dias_credito'               => 'required_if:credito,true|integer|min:1',
            'subtotal'                   => 'required|numeric|min:0',
            'impuestos'                  => 'numeric|min:0',
            'total'                      => 'required|numeric|min:0',
            'detalles'                   => 'required|array|min:1',
            'detalles.*.articulo_id'     => 'required|exists:articulos,id',
            'detalles.*.lote_id'         => 'required|exists:inventario,id',
            'detalles.*.cantidad'        => 'required|numeric|min:0.001',
            'detalles.*.empaque'         => 'numeric|min:0',
            'detalles.*.precio'          => 'required|numeric|min:0',
            'detalles.*.impuestos'       => 'numeric|min:0',
            'empleado_id'                => 'nullable|exists:empleados,id',
        ]);

        DB::beginTransaction();

        try {
            // 1. Validar stock de cada lote antes de proceder
            foreach ($request->detalles as $det) {
                $inventario = Inventario::find($det['lote_id']);

                if (!$inventario) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Lote #{$det['lote_id']} no encontrado.",
                    ], 422);
                }

                // Verificar que el lote corresponde al almacén seleccionado
                if ($inventario->almacen_id != $request->almacen_id) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "El lote #{$det['lote_id']} no pertenece al almacén seleccionado.",
                    ], 422);
                }

                if (!$inventario->hasStock($det['cantidad'])) {
                    $articulo = \App\Models\Articulo::find($det['articulo_id']);
                    DB::rollBack();
                    return response()->json([
                        'message' => "Stock insuficiente para '{$articulo->nombre}' variedad '{$inventario->variedad}'. Disponible: {$inventario->existencia}",
                    ], 422);
                }
            }

            // 2. Crear la venta
            $venta = Venta::create([
                'fecha'       => $request->fecha,
                'cliente_id'  => $request->cliente_id,
                'almacen_id'  => $request->almacen_id,
                'user_id'     => auth()->id(),
                'empleado_id' => $request->empleado_id,
                'f_pago_id'   => $request->f_pago_id,
                'credito'     => $request->boolean('credito', false),
                'subtotal'    => $request->subtotal,
                'impuestos'   => $request->impuestos ?? 0,
                'total'       => $request->total,
            ]);

            // 3. Procesar cada detalle
            foreach ($request->detalles as $det) {
                $inventario = Inventario::find($det['lote_id']);

                // 3a. Decrementar existencia
                $inventario->decreaseStock($det['cantidad']);

                // 3b. Crear VentaDetalle — lote_id = Inventario.id
                VentaDetalle::create([
                    'venta_id'    => $venta->id,
                    'articulo_id' => $det['articulo_id'],
                    'lote_id'     => $inventario->id,
                    'cantidad'    => $det['cantidad'],
                    'empaque'     => $det['empaque'] ?? 0,
                    'precio'      => $det['precio'],
                    'impuestos'   => $det['impuestos'] ?? 0,
                ]);

                // 3c. Registrar salida en Kardex — lote_id = Inventario.id
                Kardex::registrarSalida([
                    'lote_id'    => $inventario->id,
                    'fecha'      => $request->fecha,
                    'movimiento' => 'Venta',
                    'documento'  => 'VTA' . str_pad($venta->id, 6, '0', STR_PAD_LEFT),
                    'cliente_id' => $request->cliente_id,
                    'cantidad'   => $det['cantidad'],
                    'empaque'    => $det['empaque'] ?? 0,
                    'costo'      => $inventario->costo,
                    'precio'     => $det['precio'],
                ]);
            }

            // 4. Crédito → crear CtaXCobrar
            if ($request->boolean('credito')) {
                $vencimiento = \Carbon\Carbon::parse($request->fecha)
                    ->addDays($request->dias_credito)
                    ->toDateString();

                CtaXCobrar::create([
                    'fecha'      => $request->fecha,
                    'vencimiento'=> $vencimiento,
                    'cliente_id' => $request->cliente_id,
                    'venta_id'   => $venta->id,
                    'importe'    => $request->total,
                    'saldo'      => $request->total,
                ]);
            } else {
                // 5. Contado → registrar entrada en Caja
                Caja::entrada(
                    $request->total,
                    'VTA' . str_pad($venta->id, 6, '0', STR_PAD_LEFT) . ' - ' . $request->fecha,
                    $venta->almacen_id
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Venta registrada correctamente.',
                'venta'   => $venta->load(['detalles.articulo', 'cliente', 'almacen', 'formaPago']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar la venta.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Venta $venta)
    {
        return response()->json(
            $venta->load([
                'cliente',
                'almacen',
                'user',
                'formaPago',
                'detalles.articulo',
                'detalles.lote',          // CompraDetalle con número de lote string
                'ctaPorCobrar.detalles.formaPago',
            ])
        );
    }

    /**
     * Cancelar venta — devuelve existencias
     */
    public function cancelar(Request $request, Venta $venta)
    {
        if ($venta->estatus === 'cancelada') {
            return response()->json(['message' => 'La venta ya está cancelada.'], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($venta->detalles as $det) {
                // Devolver existencia al inventario (lote_id = Inventario.id)
                $inventario = Inventario::find($det->lote_id);

                if ($inventario) {
                    $inventario->increaseStock($det->cantidad);

                    // Kardex de reversa
                    Kardex::registrarEntrada([
                        'lote_id'      => $inventario->id,
                        'fecha'        => now()->toDateString(),
                        'movimiento'   => 'Cancelación Venta',
                        'documento'    => 'VTA' . str_pad($venta->id, 6, '0', STR_PAD_LEFT),
                        'proveedor_id' => $venta->cliente_id,
                        'cantidad'     => $det->cantidad,
                        'empaque'      => $det->empaque,
                        'costo'        => $inventario->costo,
                        'precio'       => $det->precio,
                    ]);
                }
            }

            // Cancelar CxC si no tiene abonos
            if ($venta->ctaPorCobrar && $venta->ctaPorCobrar->saldo == $venta->ctaPorCobrar->importe) {
                $venta->ctaPorCobrar->delete();
            }

            // Revertir entrada de caja si era contado
            if (!$venta->credito) {
                Caja::salida(
                    $venta->total,
                    'Cancelación VTA' . str_pad($venta->id, 6, '0', STR_PAD_LEFT),
                    $venta->almacen_id
                );
            }

            $venta->update(['estatus' => 'cancelada']);

            DB::commit();

            return response()->json(['message' => 'Venta cancelada correctamente.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al cancelar la venta.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar ticket ESC/POS para impresora térmica.
     * GET /ventas/{venta}/ticket?cols=48
     *
     * Responde con application/octet-stream.
     * El cliente envía el binario directo al puerto de la impresora.
     */
    public function ticket(Request $request, Venta $venta)
    {
        $venta->load([
            'cliente',
            'almacen',
            'user',
            'empleado',
            'formaPago',
            'detalles.articulo',
            'detalles.lote',
        ]);

        $cols   = (int) $request->get('cols', 48);
        $ticket = new TicketEscPos($cols);

        $folio  = 'VTA' . str_pad($venta->id, 6, '0', STR_PAD_LEFT);
        $fecha  = $venta->fecha->format('d/m/Y');

        // ── Encabezado ────────────────────────────────────────────────────
        $ticket->doubleLine()
               ->bigCenter($venta->almacen->descripcion)
               ->feed();

        if ($venta->almacen->direccion) {
            $ticket->center($venta->almacen->direccion);
        }

        $ciudadTel = trim(
            ($venta->almacen->ciudad ?? '') .
            ($venta->almacen->telefono ? '  Tel: ' . $venta->almacen->telefono : '')
        );
        if ($ciudadTel) {
            $ticket->center($ciudadTel);
        }

        $ticket->doubleLine()
               ->row('FOLIO: ' . $folio, 'FECHA: ' . $fecha)
               ->left('CLIENTE: ' . mb_strtoupper($venta->cliente->nombre))
               ->left('VENDEDOR: ' . mb_strtoupper($venta->user->name));

        if ($venta->empleado) {
            $ticket->left('ENTREGO: ' . mb_strtoupper($venta->empleado->nombre));
        }

        $pago = $venta->credito
            ? 'CRÉDITO'
            : mb_strtoupper($venta->formaPago->descripcion ?? 'CONTADO');
        $ticket->left('PAGO: ' . $pago);

        // ── Detalle ───────────────────────────────────────────────────────
        $ticket->line();

        if ($cols >= 48) {
            $ticket->detailRow('ARTÍCULO', 'CANT', 'PRECIO', 'IMPORTE');
        }

        $ticket->line();

        foreach ($venta->detalles as $det) {
            $nombre = $det->articulo->nombre;
            if ($det->lote && $det->lote->variedad) {
                $nombre .= ' ' . $det->lote->variedad;
            }

            $ticket->detailRow(
                $nombre,
                number_format((float) $det->cantidad, 2),
                TicketEscPos::money((float) $det->precio),
                TicketEscPos::money((float) $det->cantidad * (float) $det->precio)
            );
        }

        // ── Totales ───────────────────────────────────────────────────────
        $totalKilos = $venta->detalles->sum(fn($det) => (float) $det->cantidad);

        $ticket->line()
               ->row('TOTAL DE KILOS:', number_format($totalKilos, 2))
               ->row('SUBTOTAL:', TicketEscPos::money((float) $venta->subtotal));

        if ((float) $venta->impuestos > 0) {
            $ticket->row('IMPUESTOS:', TicketEscPos::money((float) $venta->impuestos));
        }

        $ticket->doubleLine();

        // Total en negrita y tamaño doble
        $totalStr = TicketEscPos::money((float) $venta->total);
        $pad      = max(1, $cols - mb_strlen('TOTAL:') - mb_strlen($totalStr));
        $ticket->left(
            TicketEscPos::BOLD_ON .
            'TOTAL:' . str_repeat(' ', $pad) . $totalStr .
            TicketEscPos::BOLD_OFF
        );

        // ── Pie ───────────────────────────────────────────────────────────
        $ticket->doubleLine()
               ->center('*** GRACIAS POR SU COMPRA ***')
               ->doubleLine()
               ->cut();

        return response($ticket->get(), 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $folio . '.bin"',
        ]);
    }

    /**
     * Consultar lotes disponibles para venta
     * (Inventario con existencia > 0 filtrado por almacén y artículo)
     */
    public function lotesDisponibles(Request $request)
    {
        $request->validate([
            'almacen_id'  => 'required|exists:almacenes,id',
            'articulo_id' => 'nullable|exists:articulos,id',
        ]);

        $query = Inventario::with('articulo.categoria')
            ->where('almacen_id', $request->almacen_id)
            ->where('existencia', '>', 0);

        if ($request->filled('articulo_id')) {
            $query->where('articulo_id', $request->articulo_id);
        }

        if ($request->filled('variedad')) {
            $query->where('variedad', 'like', '%' . $request->variedad . '%');
        }

        return response()->json($query->orderBy('articulo_id')->get());
    }
}
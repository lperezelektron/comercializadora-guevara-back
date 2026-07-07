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
use Illuminate\Support\Facades\Storage;

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
            'ctaPorCobrar',
        ]);

        $cols   = (int) $request->get('cols', 42);
        $folio  = 'VTA' . str_pad($venta->id, 6, '0', STR_PAD_LEFT);
        $ticket = new TicketEscPos($cols);

        // ── Ticket original (+ Pagaré si es crédito) ─────────────────────
        $this->buildCuerpoTicket($venta, $ticket, $cols);
        $ticket->doubleLine()
               ->center('*** GRACIAS POR SU COMPRA ***')
               ->center('Agradecemos su preferencia.')
               ->doubleLine();

        if ($venta->credito) {
            $this->buildPagare($venta, $ticket, $cols);
        }

        $ticket->cut($venta->credito); // parcial si sigue la copia

        // ── Copia (solo ventas a crédito) ────────────────────────────────
        if ($venta->credito) {
            $this->buildCuerpoTicket($venta, $ticket, $cols, 'COPIA');
            $ticket->doubleLine()
                   ->center('*** GRACIAS POR SU COMPRA ***')
                   ->center('Agradecemos su preferencia.')
                   ->doubleLine()
                   ->center('*** C O P I A ***', true)
                   ->doubleLine()
                   ->cut();
        }

        return response($ticket->get(), 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $folio . '.bin"',
        ]);
    }

    /** Imprime el cuerpo completo del ticket (encabezado, detalle y totales). */
    private function buildCuerpoTicket(Venta $venta, TicketEscPos $ticket, int $cols, string $etiqueta = ''): void
    {
        $folio   = 'VTA' . str_pad($venta->id, 6, '0', STR_PAD_LEFT);
        $almacen = $venta->almacen;

        // ── Encabezado ────────────────────────────────────────────────────
        $ticket->doubleLine();

        if ($etiqueta) {
            $ticket->center('-- ' . $etiqueta . ' --', true);
        }

        $textLines = array_values(array_filter([
            $almacen->descripcion,
            $almacen->direccion ?: null,
            trim(($almacen->ciudad ?? '') . ($almacen->telefono ? '  Tel: ' . $almacen->telefono : '')) ?: null,
        ]));

        if ($almacen->imagen) {
            try {
                $logoData = Storage::disk('public')->get($almacen->imagen);
                $ticket->addLogoHeader($logoData, $textLines);
            } catch (\Throwable) {
                $this->textHeader($ticket, $almacen);
            }
        } else {
            $this->textHeader($ticket, $almacen);
        }

        $ticket->doubleLine()
               ->row('FOLIO: ' . $folio, $venta->created_at->format('d/m/Y h:i A'))
               ->left('CLIENTE: ' . mb_strtoupper($venta->cliente->nombre));

        if ($venta->empleado) {
            $ticket->left('ENTREGO: ' . mb_strtoupper($venta->empleado->nombre));
        }

        $pago = $venta->credito
            ? 'CRÉDITO'
            : mb_strtoupper($venta->formaPago->descripcion ?? 'CONTADO');
        $ticket->left('PAGO: ' . $pago);

        // ── Detalle ───────────────────────────────────────────────────────
        $ticket->line();

        if ($cols >= 42) {
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
                self::formatCantidad((float) $det->cantidad),
                TicketEscPos::money((float) $det->precio),
                TicketEscPos::money(round((float) $det->cantidad * (float) $det->precio, 0))
            );
        }

        // ── Totales ───────────────────────────────────────────────────────
        $totalArticulos = $venta->detalles->count();

        $ticket->line()
               ->row('ARTÍCULOS:', (string) $totalArticulos)
               ->row('SUBTOTAL:', TicketEscPos::money((float) $venta->subtotal));

        if ((float) $venta->impuestos > 0) {
            $ticket->row('IMPUESTOS:', TicketEscPos::money((float) $venta->impuestos));
        }

        $ticket->doubleLine();

        $totalStr = TicketEscPos::money((float) $venta->total);
        $pad      = max(1, $cols - mb_strlen('TOTAL:') - mb_strlen($totalStr));
        $ticket->left(
            TicketEscPos::BOLD_ON .
            'TOTAL:' . str_repeat(' ', $pad) . $totalStr .
            TicketEscPos::BOLD_OFF
        );
    }

    /** Cabecera solo texto cuando no hay logo. */
    private function textHeader(TicketEscPos $ticket, \App\Models\Almacen $almacen): void
    {
        $ticket->bigCenter($almacen->descripcion)->feed();

        if ($almacen->direccion) {
            $ticket->center($almacen->direccion);
        }

        $ciudadTel = trim(
            ($almacen->ciudad ?? '') .
            ($almacen->telefono ? '  Tel: ' . $almacen->telefono : '')
        );
        if ($ciudadTel) {
            $ticket->center($ciudadTel);
        }
    }

    /** Muestra entero si no hay decimales, o 3 decimales si los hay. */
    private static function formatCantidad(float $val): string
    {
        $rounded = round($val, 3);
        if ($rounded == floor($rounded)) {
            return number_format((int) $rounded, 0);
        }
        return number_format($rounded, 3);
    }

    /** Imprime el pagaré para ventas a crédito. */
    private function buildPagare(Venta $venta, TicketEscPos $ticket, int $cols): void
    {
        $empresa    = mb_strtoupper($venta->almacen->descripcion);
        $total      = TicketEscPos::money((float) $venta->total);
        $vencimiento = $venta->ctaPorCobrar
            ? $venta->ctaPorCobrar->vencimiento->format('d/m/Y')
            : '___/___/______';

        $texto =
            'Por este pagaré me (nos) obligo (amos) a pagar solidaria, ' .
            'mancomunada, e incondicionalmente a la orden de: ' . $empresa . ' ' .
            'la cantidad de: ' . $total . ' ' .
            'el día ' . $vencimiento . ' ' .
            'por haber recibido a mi entera satisfacción la mercancia descrita. ' .
            'De no ser pagado el vencimiento estipula causará un interes del 4% ' .
            'mensual apartir de la fecha del presente documento.';

        $ticket->doubleLine()
               ->center('P A G A R É', true)
               ->doubleLine();

        foreach (explode("\n", wordwrap($texto, $cols, "\n", false)) as $linea) {
            $ticket->left($linea);
        }

        $ticket->feed(3)
               ->left('NOMBRE: ' . str_repeat('_', max(4, $cols - 8)))
               ->feed(2)
               ->left('FIRMA:  ' . str_repeat('_', max(4, $cols - 8)))
               ->feed(2)
               ->doubleLine();
    }

    /**
     * Resumen diario: efectivo, otras formas de pago, crédito y recuperado.
     * GET /ventas/reportes/diario?fecha=&almacen_id=
     */
    public function resumenDiario(Request $request)
    {
        $request->validate([
            'fecha'      => 'required|date',
            'almacen_id' => 'nullable|exists:almacenes,id',
        ]);

        $fecha     = $request->fecha;
        $almacenId = $request->filled('almacen_id') ? $request->almacen_id : null;

        // Ventas de contado agrupadas por forma de pago
        $contadoRows = Venta::join('forma_pago', 'ventas.f_pago_id', '=', 'forma_pago.id')
            ->where('ventas.credito', false)
            ->whereDate('ventas.fecha', $fecha)
            ->when($almacenId, fn($q) => $q->where('ventas.almacen_id', $almacenId))
            ->selectRaw('
                forma_pago.id as f_pago_id,
                forma_pago.descripcion as forma_pago,
                COUNT(*) as tickets,
                SUM(ventas.total) as total
            ')
            ->groupBy('forma_pago.id', 'forma_pago.descripcion')
            ->get();

        $efectivoRow = $contadoRows->first(fn($r) => strtolower($r->forma_pago) === 'efectivo');
        $otrasRows   = $contadoRows->filter(fn($r) => strtolower($r->forma_pago) !== 'efectivo')->values();

        $totalEfectivo   = (float) ($efectivoRow?->total ?? 0);
        $ticketsEfectivo = (int)   ($efectivoRow?->tickets ?? 0);
        $totalOtras      = (float)  $otrasRows->sum('total');
        $totalContado    = $totalEfectivo + $totalOtras;

        // Ventas a crédito del día (con detalle de cliente/total para el desglose)
        $ventasCredito = Venta::with('cliente:id,nombre')
            ->where('credito', true)
            ->whereDate('fecha', $fecha)
            ->when($almacenId, fn($q) => $q->where('almacen_id', $almacenId))
            ->get();

        $totalCredito   = (float) $ventasCredito->sum('total');
        $ticketsCredito = $ventasCredito->count();

        $ticketsCreditoDetalle = $ventasCredito->map(fn($v) => [
            'cliente' => $v->cliente->nombre ?? null,
            'total'   => (float) $v->total,
        ])->values();

        // Recuperado del día: abonos de CxC registrados en la fecha
        $recuperadoQuery = \App\Models\CxcDetalle::whereDate('fecha', $fecha);
        if ($almacenId) {
            $recuperadoQuery->whereHas('ctaXCobrar.venta', fn($q) => $q->where('almacen_id', $almacenId));
        }
        $totalRecuperado = (float) $recuperadoQuery->sum('importe');
        $numAbonos       = (int)   (clone $recuperadoQuery)->count();

        return response()->json([
            'fecha'                   => $fecha,
            'efectivo'                => ['total' => $totalEfectivo,   'tickets' => $ticketsEfectivo],
            'otras_formas_pago'       => $otrasRows,
            'total_otras_formas_pago' => $totalOtras,
            'subtotal_contado'        => $totalContado,
            'credito'                 => ['total' => $totalCredito, 'tickets' => $ticketsCredito, 'ventas' => $ticketsCreditoDetalle],
            'total_venta_dia'         => $totalContado + $totalCredito,
            'recuperado'              => ['total' => $totalRecuperado, 'abonos' => $numAbonos],
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

    /**
     * Resumen de ventas por artículo en un rango de fechas.
     * GET /ventas/reportes/por-articulo?fecha_inicio=&fecha_fin=&almacen_id=
     */
    public function resumenPorArticulo(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'almacen_id'   => 'nullable|exists:almacenes,id',
        ]);

        $articulos = VentaDetalle::join('ventas', 'ventas_detalle.venta_id', '=', 'ventas.id')
            ->join('articulos', 'ventas_detalle.articulo_id', '=', 'articulos.id')
            ->whereBetween('ventas.fecha', [$request->fecha_inicio, $request->fecha_fin])
            ->when($request->filled('almacen_id'), fn($q) => $q->where('ventas.almacen_id', $request->almacen_id))
            ->selectRaw('
                articulos.id,
                articulos.nombre,
                articulos.unidad,
                SUM(ventas_detalle.cantidad) as cantidad,
                SUM(ventas_detalle.cantidad * ventas_detalle.precio) as subtotal,
                SUM(ventas_detalle.impuestos) as impuestos,
                SUM(ventas_detalle.cantidad * ventas_detalle.precio + ventas_detalle.impuestos) as total
            ')
            ->groupBy('articulos.id', 'articulos.nombre', 'articulos.unidad')
            ->orderBy('articulos.nombre')
            ->get();

        return response()->json([
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin'    => $request->fecha_fin,
            'articulos'    => $articulos,
            'totales'      => [
                'cantidad'  => $articulos->sum('cantidad'),
                'subtotal'  => $articulos->sum('subtotal'),
                'impuestos' => $articulos->sum('impuestos'),
                'total'     => $articulos->sum('total'),
            ],
        ]);
    }

    /**
     * Resumen de ventas por forma de pago (incluye crédito) en un rango de fechas.
     * GET /ventas/reportes/formas-pago?fecha_inicio=&fecha_fin=&almacen_id=
     */
    public function resumenFormasPago(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'almacen_id'   => 'nullable|exists:almacenes,id',
        ]);

        $contado = Venta::join('forma_pago', 'ventas.f_pago_id', '=', 'forma_pago.id')
            ->where('ventas.credito', false)
            ->whereBetween('ventas.fecha', [$request->fecha_inicio, $request->fecha_fin])
            ->when($request->filled('almacen_id'), fn($q) => $q->where('ventas.almacen_id', $request->almacen_id))
            ->selectRaw('
                forma_pago.id as f_pago_id,
                forma_pago.descripcion as forma_pago,
                COUNT(*) as tickets,
                SUM(ventas.total) as total
            ')
            ->groupBy('forma_pago.id', 'forma_pago.descripcion')
            ->orderBy('forma_pago.descripcion')
            ->get();

        $credito = Venta::where('credito', true)
            ->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin])
            ->when($request->filled('almacen_id'), fn($q) => $q->where('almacen_id', $request->almacen_id))
            ->selectRaw('COUNT(*) as tickets, SUM(total) as total')
            ->first();

        $formasPago = $contado->push((object) [
            'f_pago_id'  => null,
            'forma_pago' => 'Crédito',
            'tickets'    => (int) ($credito->tickets ?? 0),
            'total'      => (float) ($credito->total ?? 0),
        ]);

        return response()->json([
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin'    => $request->fecha_fin,
            'formas_pago'  => $formasPago,
            'totales'      => [
                'tickets' => $formasPago->sum('tickets'),
                'total'   => $formasPago->sum('total'),
            ],
        ]);
    }
}
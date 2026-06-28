<?php
// ============================================================
// app/Http/Controllers/Api/ReporteController.php
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Compra;
use App\Models\Inventario;
use App\Models\CtaXCobrar;
use App\Models\CtaXPagar;
use App\Models\Caja;
use App\Models\Kardex;
use App\Models\CxcDetalle;
use App\Models\CxpDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    /**
     * Dashboard general
     */
    public function dashboard()
    {
        $hoy      = now()->toDateString();
        $mes      = now()->month;
        $anio     = now()->year;

        return response()->json([
            'ventas_hoy'          => Venta::whereDate('fecha', $hoy)->sum('total'),
            'ventas_mes'          => Venta::delMes($mes, $anio)->sum('total'),
            'compras_mes'         => Compra::delMes($mes, $anio)->sum('total'),
            'tickets_hoy'         => Venta::whereDate('fecha', $hoy)->count(),
            'cxc_pendiente'       => CtaXCobrar::pendientes()->sum('saldo'),
            'cxc_vencida'         => CtaXCobrar::vencidas()->sum('saldo'),
            'cxp_pendiente'       => CtaXPagar::pendientes()->sum('saldo'),
            'cxp_vencida'         => CtaXPagar::vencidas()->sum('saldo'),
            'saldo_caja'          => Caja::saldoActual(),
            'saldo_caja_hoy'      => Caja::saldoDelDia($hoy),
        ]);
    }

    /**
     * Ventas agrupadas por día/mes
     */
    public function ventas(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'agrupar'      => 'in:dia,mes',
        ]);

        $formato = $request->agrupar === 'mes' ? '%Y-%m' : '%Y-%m-%d';

        $ventas = Venta::whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin])
            ->when($request->filled('almacen_id'), fn($q) => $q->porAlmacen($request->almacen_id))
            ->when($request->filled('cliente_id'), fn($q) => $q->porCliente($request->cliente_id))
            ->selectRaw("DATE_FORMAT(fecha, '{$formato}') as periodo,
                         COUNT(*) as tickets,
                         SUM(subtotal) as subtotal,
                         SUM(impuestos) as impuestos,
                         SUM(total) as total")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get();

        return response()->json([
            'ventas'  => $ventas,
            'totales' => [
                'tickets'   => $ventas->sum('tickets'),
                'subtotal'  => $ventas->sum('subtotal'),
                'impuestos' => $ventas->sum('impuestos'),
                'total'     => $ventas->sum('total'),
            ],
        ]);
    }

    /**
     * Artículos más vendidos
     */
    public function topArticulos(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'limit'        => 'integer|min:1|max:50',
        ]);

        $top = VentaDetalle::join('ventas', 'ventas_detalle.venta_id', '=', 'ventas.id')
            ->join('articulos', 'ventas_detalle.articulo_id', '=', 'articulos.id')
            ->join('inventario', 'ventas_detalle.lote_id', '=', 'inventario.id')
            ->whereBetween('ventas.fecha', [$request->fecha_inicio, $request->fecha_fin])
            ->when($request->filled('almacen_id'), fn($q) => $q->where('ventas.almacen_id', $request->almacen_id))
            ->selectRaw('
                articulos.id,
                articulos.nombre,
                articulos.unidad,
                inventario.variedad,
                SUM(ventas_detalle.cantidad) as total_cantidad,
                SUM(ventas_detalle.empaque)  as total_empaques,
                SUM(ventas_detalle.precio * ventas_detalle.cantidad) as total_importe,
                SUM((ventas_detalle.precio - inventario.costo) * ventas_detalle.cantidad) as utilidad
            ')
            ->groupBy('articulos.id', 'articulos.nombre', 'articulos.unidad', 'inventario.variedad')
            ->orderByDesc('total_cantidad')
            ->limit($request->limit ?? 20)
            ->get();

        return response()->json($top);
    }

    /**
     * Inventario valorizado
     */
    public function inventarioValorizado(Request $request)
    {
        $query = Inventario::join('articulos', 'inventario.articulo_id', '=', 'articulos.id')
            ->join('almacenes', 'inventario.almacen_id', '=', 'almacenes.id')
            ->join('categorias', 'articulos.categoria_id', '=', 'categorias.id')
            ->where('inventario.existencia', '>', 0)
            ->selectRaw('
                articulos.nombre,
                articulos.unidad,
                categorias.descripcion as categoria,
                almacenes.descripcion as almacen,
                inventario.variedad,
                inventario.existencia,
                inventario.empaque,
                inventario.costo,
                inventario.precio,
                inventario.existencia * inventario.costo  as valor_costo,
                inventario.existencia * inventario.precio as valor_precio
            ')
            ->when($request->filled('almacen_id'), fn($q) => $q->where('inventario.almacen_id', $request->almacen_id))
            ->when($request->filled('categoria_id'), fn($q) => $q->where('articulos.categoria_id', $request->categoria_id))
            ->orderBy('categorias.descripcion')
            ->orderBy('articulos.orden')
            ->orderBy('articulos.nombre');

        $resultado = $query->get();

        return response()->json([
            'inventario'    => $resultado,
            'total_costo'   => $resultado->sum('valor_costo'),
            'total_precio'  => $resultado->sum('valor_precio'),
            'total_lineas'  => $resultado->count(),
        ]);
    }

    /**
     * Estado de Resultados por período.
     * GET /reportes/estado-resultados?fecha_inicio=&fecha_fin=&almacen_id=
     */
    public function estadoResultados(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'almacen_id'   => 'nullable|exists:almacenes,id',
        ]);

        $fi        = $request->fecha_inicio;
        $ff        = $request->fecha_fin;
        $almacenId = $request->filled('almacen_id') ? $request->almacen_id : null;

        // ── 1. Ventas del período ─────────────────────────────────────────
        $ventasBaseQ = Venta::whereBetween('fecha', [$fi, $ff])
            ->when($almacenId, fn($q) => $q->where('almacen_id', $almacenId));

        $ventasContado = (float) (clone $ventasBaseQ)->where('credito', false)->sum('total');
        $ventasCredito = (float) (clone $ventasBaseQ)->where('credito', true)->sum('total');
        $totalVentas   = $ventasContado + $ventasCredito;
        $ticketsTotal  = (int)   (clone $ventasBaseQ)->count();

        // ── 2. Crédito pendiente del período (aún sin cobrar) ─────────────
        $cxcPendQ = CtaXCobrar::where('saldo', '>', 0)
            ->whereHas('venta', function ($q) use ($fi, $ff, $almacenId) {
                $q->whereBetween('fecha', [$fi, $ff]);
                if ($almacenId) $q->where('almacen_id', $almacenId);
            });

        $creditoPendiente = (float) (clone $cxcPendQ)->sum('saldo');
        $numCuentasPend   = (int)   (clone $cxcPendQ)->count();

        // ── 3. Recuperación (abonos en el período de ventas de otros períodos) ─
        $recupQ = CxcDetalle::whereBetween('fecha', [$fi, $ff])
            ->whereHas('ctaXCobrar.venta', function ($q) use ($fi, $ff, $almacenId) {
                $q->where(fn($i) => $i->where('fecha', '<', $fi)->orWhere('fecha', '>', $ff));
                if ($almacenId) $q->where('almacen_id', $almacenId);
            });

        $totalRecuperacion = (float) (clone $recupQ)->sum('importe');
        $numRecuperacion   = (int)   (clone $recupQ)->count();

        // ── 4. Costo de ventas del período (Kardex salidas tipo Venta) ────
        $costoQ = Kardex::where('tipo', 'salida')
            ->where('movimiento', 'Venta')
            ->whereBetween('fecha', [$fi, $ff]);

        if ($almacenId) {
            $costoQ->whereHas('lote', fn($q) => $q->where('almacen_id', $almacenId));
        }

        $costoVentas = (float) ($costoQ->selectRaw('SUM(cantidad * costo) as total')->value('total') ?? 0);

        // ── 5. Compras del período pagadas ───────────────────────────────
        $comprasBaseQ = Compra::whereBetween('fecha', [$fi, $ff])
            ->when($almacenId, fn($q) => $q->where('almacen_id', $almacenId));

        $comprasContado       = (float) (clone $comprasBaseQ)->whereDoesntHave('ctaPorPagar')->sum('total');
        $comprasCreditoSaldado = (float) (clone $comprasBaseQ)
            ->whereHas('ctaPorPagar', fn($q) => $q->where('saldo', 0))
            ->sum('total');
        $totalComprasPagadas  = $comprasContado + $comprasCreditoSaldado;
        $numComprasPagadas    = (int) (clone $comprasBaseQ)
            ->where(fn($q) => $q
                ->whereDoesntHave('ctaPorPagar')
                ->orWhereHas('ctaPorPagar', fn($i) => $i->where('saldo', 0)))
            ->count();

        // ── 6. Pagos a proveedores de compras de otros períodos ──────────
        $pagProvQ = CxpDetalle::whereBetween('fecha', [$fi, $ff])
            ->where('tipo', 'abono')
            ->whereHas('ctaXPagar.compra', function ($q) use ($fi, $ff, $almacenId) {
                $q->where(fn($i) => $i->where('fecha', '<', $fi)->orWhere('fecha', '>', $ff));
                if ($almacenId) $q->where('almacen_id', $almacenId);
            });

        $totalPagosProveedores = (float) (clone $pagProvQ)->sum('importe');
        $numPagosProveedores   = (int)   (clone $pagProvQ)->count();

        // ── 7. Gastos adicionales (salidas manuales de caja) ─────────────
        $gastosQ = Caja::where('tipo', 'salida')
            ->whereBetween('fecha', [$fi, $ff])
            ->when($almacenId, fn($q) => $q->where('almacen_id', $almacenId))
            ->where('referencia', 'not like', 'Compra #%')
            ->where('referencia', 'not like', 'Pago CxP #%')
            ->where('referencia', 'not like', 'Cancelación VTA%');

        $gastosRows  = $gastosQ->select(['fecha', 'referencia', 'cantidad'])->orderBy('fecha')->get();
        $totalGastos = (float) $gastosRows->sum('cantidad');

        return response()->json([
            'fecha_inicio'      => $fi,
            'fecha_fin'         => $ff,
            'ventas_periodo'    => [
                'total'   => $totalVentas,
                'tickets' => $ticketsTotal,
                'contado' => $ventasContado,
                'credito' => $ventasCredito,
            ],
            'credito_pendiente' => [
                'total'       => $creditoPendiente,
                'num_cuentas' => $numCuentasPend,
            ],
            'recuperacion'      => [
                'total'  => $totalRecuperacion,
                'abonos' => $numRecuperacion,
            ],
            'costo_ventas'      => $costoVentas,
            'compras_pagadas'   => [
                'total'           => $totalComprasPagadas,
                'contado'         => $comprasContado,
                'credito_saldado' => $comprasCreditoSaldado,
                'num_compras'     => $numComprasPagadas,
            ],
            'pagos_proveedores' => [
                'total'     => $totalPagosProveedores,
                'num_pagos' => $numPagosProveedores,
            ],
            'gastos_adicionales' => [
                'total'       => $totalGastos,
                'movimientos' => $gastosRows,
            ],
        ]);
    }

    /**
     * Utilidad bruta por período
     */
    public function utilidad(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $resultado = VentaDetalle::join('ventas', 'ventas_detalle.venta_id', '=', 'ventas.id')
            ->join('inventario', 'ventas_detalle.lote_id', '=', 'inventario.id')
            ->whereBetween('ventas.fecha', [$request->fecha_inicio, $request->fecha_fin])
            ->when($request->filled('almacen_id'), fn($q) => $q->where('ventas.almacen_id', $request->almacen_id))
            ->selectRaw('
                SUM(ventas_detalle.cantidad * ventas_detalle.precio) as total_ventas,
                SUM(ventas_detalle.cantidad * inventario.costo)      as total_costo,
                SUM(ventas_detalle.cantidad * (ventas_detalle.precio - inventario.costo)) as utilidad_bruta
            ')
            ->first();

        $totalVentas   = $resultado->total_ventas ?? 0;
        $totalCosto    = $resultado->total_costo  ?? 0;
        $utilidadBruta = $resultado->utilidad_bruta ?? 0;
        $margen        = $totalVentas > 0 ? round(($utilidadBruta / $totalVentas) * 100, 2) : 0;

        return response()->json([
            'fecha_inicio'   => $request->fecha_inicio,
            'fecha_fin'      => $request->fecha_fin,
            'total_ventas'   => $totalVentas,
            'total_costo'    => $totalCosto,
            'utilidad_bruta' => $utilidadBruta,
            'margen_pct'     => $margen,
        ]);
    }
}

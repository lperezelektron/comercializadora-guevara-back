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

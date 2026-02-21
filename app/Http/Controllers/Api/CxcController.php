<?php
// ============================================================
// app/Http/Controllers/Api/CxcController.php
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CtaXCobrar;
use Illuminate\Http\Request;

class CxcController extends Controller
{
    public function index(Request $request)
    {
        $query = CtaXCobrar::with(['cliente', 'venta'])
            ->orderBy('vencimiento');

        if ($request->filled('cliente_id')) {
            $query->porCliente($request->cliente_id);
        }

        // Por defecto sólo pendientes
        if ($request->boolean('todas')) {
            // sin filtro
        } elseif ($request->boolean('vencidas')) {
            $query->vencidas();
        } else {
            $query->pendientes();
        }

        return response()->json(
            $request->filled('per_page')
                ? $query->paginate($request->per_page)
                : $query->get()
        );
    }

    public function show(CtaXCobrar $ctaXCobrar)
    {
        return response()->json(
            $ctaXCobrar->load(['cliente', 'venta.detalles.articulo', 'detalles.formaPago'])
        );
    }

    /**
     * Registrar abono
     */
    public function abonar(Request $request, CtaXCobrar $ctaXCobrar)
    {
        if ($ctaXCobrar->estaSaldada()) {
            return response()->json(['message' => 'Esta cuenta ya está liquidada.'], 422);
        }

        $request->validate([
            'importe'    => 'required|numeric|min:0.01|max:' . $ctaXCobrar->saldo,
            'f_pago_id'  => 'required|exists:forma_pago,id',
        ]);

        try {
            $ctaXCobrar->abonar($request->importe, $request->f_pago_id);

            return response()->json([
                'message'     => 'Abono registrado correctamente.',
                'nuevo_saldo' => $ctaXCobrar->fresh()->saldo,
                'saldada'     => $ctaXCobrar->fresh()->estaSaldada(),
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Resumen general de CxC
     */
    public function resumen()
    {
        $hoy = now()->toDateString();

        return response()->json([
            'total_pendiente'  => CtaXCobrar::pendientes()->sum('saldo'),
            'total_vencido'    => CtaXCobrar::vencidas()->sum('saldo'),
            'por_vencer_7dias' => CtaXCobrar::pendientes()
                ->whereBetween('vencimiento', [$hoy, now()->addDays(7)->toDateString()])
                ->sum('saldo'),
            'num_cuentas'      => CtaXCobrar::pendientes()->count(),
            'num_vencidas'     => CtaXCobrar::vencidas()->count(),
        ]);
    }
}
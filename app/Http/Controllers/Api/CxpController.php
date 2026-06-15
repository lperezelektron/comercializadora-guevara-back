<?php
// ============================================================
// app/Http/Controllers/Api/CxpController.php
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CtaXPagar;
use Illuminate\Http\Request;

class CxpController extends Controller
{
    public function index(Request $request)
    {
        $query = CtaXPagar::with(['proveedor', 'compra'])
            ->orderBy('vencimiento');

        if ($request->filled('proveedor_id')) {
            $query->porProveedor($request->proveedor_id);
        }

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

    public function show(CtaXPagar $ctaXPagar)
    {
        return response()->json(
            $ctaXPagar->load(['proveedor', 'compra.detalles.inventario.articulo', 'detalles.formaPago'])
        );
    }

    /**
     * Registrar pago/abono
     */
    public function pagar(Request $request, CtaXPagar $ctaXPagar)
    {
        if ($ctaXPagar->estaSaldada()) {
            return response()->json(['message' => 'Esta cuenta ya está liquidada.'], 422);
        }

        $request->validate([
            'importe'   => 'required|numeric|min:0.01|max:' . $ctaXPagar->saldo,
            'f_pago_id' => 'required|exists:forma_pago,id',
            'tipo'      => 'in:Abono,Cargo',
        ]);

        try {
            if ($request->tipo === 'Cargo') {
                $ctaXPagar->cargo($request->importe, $request->f_pago_id);
            } else {
                $ctaXPagar->abonar($request->importe, $request->f_pago_id);
            }

            return response()->json([
                'message'     => 'Movimiento registrado correctamente.',
                'nuevo_saldo' => $ctaXPagar->fresh()->saldo,
                'saldada'     => $ctaXPagar->fresh()->estaSaldada(),
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Resumen general de CxP
     */
    public function resumen()
    {
        $hoy = now()->toDateString();

        return response()->json([
            'total_pendiente'  => CtaXPagar::pendientes()->sum('saldo'),
            'total_vencido'    => CtaXPagar::vencidas()->sum('saldo'),
            'por_vencer_7dias' => CtaXPagar::pendientes()
                ->whereBetween('vencimiento', [$hoy, now()->addDays(7)->toDateString()])
                ->sum('saldo'),
            'num_cuentas'      => CtaXPagar::pendientes()->count(),
            'num_vencidas'     => CtaXPagar::vencidas()->count(),
        ]);
    }
}
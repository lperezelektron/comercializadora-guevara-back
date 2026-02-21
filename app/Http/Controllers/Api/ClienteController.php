<?php
// ============================================================
// app/Http/Controllers/Api/ClienteController.php
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Cliente::orderBy('nombre');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->boolean('con_saldo')) {
            $query->conSaldo();
        }

        return response()->json(
            $request->filled('per_page')
                ? $query->paginate($request->per_page)
                : $query->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'    => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'ciudad'    => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:20',
            'activo'    => 'boolean',
        ]);

        $cliente = Cliente::create($request->only('nombre', 'direccion', 'ciudad', 'telefono', 'activo'));

        return response()->json([
            'message' => 'Cliente creado correctamente.',
            'cliente' => $cliente,
        ], 201);
    }

    public function show(Cliente $cliente)
    {
        return response()->json([
            'cliente'         => $cliente,
            'total_ventas'    => $cliente->totalVentas(),
            'saldo_pendiente' => $cliente->saldoPendiente(),
            'ultima_venta'    => $cliente->ultimaVenta(),
        ]);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $request->validate([
            'nombre'    => 'sometimes|required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'ciudad'    => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:20',
            'activo'    => 'boolean',
        ]);

        $cliente->update($request->only('nombre', 'direccion', 'ciudad', 'telefono', 'activo'));

        return response()->json([
            'message' => 'Cliente actualizado.',
            'cliente' => $cliente,
        ]);
    }

    public function destroy(Cliente $cliente)
    {
        if ($cliente->ventas()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene ventas registradas.',
            ], 422);
        }

        $cliente->delete();

        return response()->json(['message' => 'Cliente eliminado.']);
    }

    /**
     * Estado de cuenta (CxC pendientes)
     */
    public function estadoCuenta(Cliente $cliente)
    {
        $cxc = $cliente->ctasPorCobrar()->pendientes()->with('venta')->get();

        return response()->json([
            'cliente'         => $cliente->only('id', 'nombre', 'telefono'),
            'cuentas'         => $cxc,
            'saldo_pendiente' => $cxc->sum('saldo'),
        ]);
    }
}
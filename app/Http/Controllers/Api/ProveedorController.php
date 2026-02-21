<?php
// ============================================================
// app/Http/Controllers/Api/ProveedorController.php
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $query = Proveedor::orderBy('nombre');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('rfc', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
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
            'nombre'       => 'required|string|max:255',
            'direccion'    => 'nullable|string|max:255',
            'ciudad'       => 'nullable|string|max:255',
            'rfc'          => 'nullable|string|max:20',
            'telefono'     => 'nullable|string|max:20',
            'dias_credito' => 'integer|min:0',
            'activo'       => 'boolean',
        ]);

        $proveedor = Proveedor::create($request->only(
            'nombre', 'direccion', 'ciudad', 'rfc', 'telefono', 'dias_credito', 'activo'
        ));

        return response()->json([
            'message'   => 'Proveedor creado correctamente.',
            'proveedor' => $proveedor,
        ], 201);
    }

    public function show(Proveedor $proveedor)
    {
        return response()->json([
            'proveedor'       => $proveedor,
            'total_compras'   => $proveedor->totalCompras(),
            'saldo_pendiente' => $proveedor->saldoPendiente(),
        ]);
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'nombre'       => 'sometimes|required|string|max:255',
            'direccion'    => 'nullable|string|max:255',
            'ciudad'       => 'nullable|string|max:255',
            'rfc'          => 'nullable|string|max:20',
            'telefono'     => 'nullable|string|max:20',
            'dias_credito' => 'integer|min:0',
            'activo'       => 'boolean',
        ]);

        $proveedor->update($request->only(
            'nombre', 'direccion', 'ciudad', 'rfc', 'telefono', 'dias_credito', 'activo'
        ));

        return response()->json([
            'message'   => 'Proveedor actualizado.',
            'proveedor' => $proveedor,
        ]);
    }

    public function destroy(Proveedor $proveedor)
    {
        if ($proveedor->compras()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene compras registradas.',
            ], 422);
        }

        $proveedor->delete();

        return response()->json(['message' => 'Proveedor eliminado.']);
    }

    /**
     * Estado de cuenta (CxP pendientes)
     */
    public function estadoCuenta(Proveedor $proveedor)
    {
        $cxp = $proveedor->ctasPorPagar()->pendientes()->with('compra')->get();

        return response()->json([
            'proveedor'       => $proveedor->only('id', 'nombre', 'rfc', 'dias_credito'),
            'cuentas'         => $cxp,
            'saldo_pendiente' => $cxp->sum('saldo'),
        ]);
    }
}
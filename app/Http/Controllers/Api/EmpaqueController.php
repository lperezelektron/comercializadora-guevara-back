<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empaque;
use Illuminate\Http\Request;

class EmpaqueController extends Controller
{
    public function index(Request $request)
    {
        $query = Empaque::orderBy('descripcion');

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'descripcion' => 'required|string|max:100|unique:empaques,descripcion',
            'dimensiones' => 'nullable|string|max:100',
            'peso'        => 'nullable|numeric|min:0',
            'existencias' => 'required|numeric|min:0',
            'activo'      => 'boolean',
        ]);

        $empaque = Empaque::create([
            'descripcion' => $request->descripcion,
            'dimensiones' => $request->dimensiones,
            'peso'        => $request->peso,
            'existencias' => $request->existencias,
            'activo'      => $request->boolean('activo', true),
        ]);

        return response()->json([
            'message' => 'Empaque creado correctamente.',
            'empaque' => $empaque,
        ], 201);
    }

    public function show(Empaque $empaque)
    {
        $empaque->load(['saldos.cliente', 'saldos' => fn ($q) => $q->where('saldo', '!=', 0)]);

        return response()->json([
            'empaque'        => $empaque,
            'total_prestado' => $empaque->totalPrestado(),
        ]);
    }

    public function update(Request $request, Empaque $empaque)
    {
        $request->validate([
            'descripcion' => 'sometimes|required|string|max:100|unique:empaques,descripcion,' . $empaque->id,
            'dimensiones' => 'nullable|string|max:100',
            'peso'        => 'nullable|numeric|min:0',
            'existencias' => 'sometimes|required|numeric|min:0',
            'activo'      => 'boolean',
        ]);

        $empaque->update($request->only('descripcion', 'dimensiones', 'peso', 'existencias', 'activo'));

        return response()->json([
            'message' => 'Empaque actualizado.',
            'empaque' => $empaque,
        ]);
    }

    public function destroy(Empaque $empaque)
    {
        if ($empaque->movimientos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el empaque tiene movimientos registrados.',
            ], 422);
        }

        $empaque->saldos()->delete();
        $empaque->delete();

        return response()->json(['message' => 'Empaque eliminado.']);
    }

    /**
     * Saldos de todos los clientes para todos los empaques.
     * GET /empaques/saldos?cliente_id=&empaque_id=&con_saldo=true
     */
    public function saldos(Request $request)
    {
        $query = \App\Models\EmpaqueClienteSaldo::with(['empaque', 'cliente'])
            ->orderBy('empaque_id')
            ->orderBy('cliente_id');

        if ($request->filled('empaque_id')) {
            $query->where('empaque_id', $request->empaque_id);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->boolean('con_saldo')) {
            $query->where('saldo', '!=', 0);
        }

        return response()->json($query->get());
    }
}

<?php
// ============================================================
// app/Http/Controllers/Api/FormaPagoController.php
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormaPago;
use Illuminate\Http\Request;

class FormaPagoController extends Controller
{
    public function index()
    {
        return response()->json(
            FormaPago::activo()->orderBy('descripcion')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'descripcion' => 'required|string|max:255|unique:forma_pago,descripcion',
            'activo'      => 'boolean',
        ]);

        $forma = FormaPago::create($request->only('descripcion', 'activo'));

        return response()->json([
            'message'    => 'Forma de pago creada.',
            'forma_pago' => $forma,
        ], 201);
    }

    public function update(Request $request, FormaPago $formaPago)
    {
        $request->validate([
            'descripcion' => 'sometimes|required|string|max:255|unique:forma_pago,descripcion,' . $formaPago->id,
            'activo'      => 'boolean',
        ]);

        $formaPago->update($request->only('descripcion', 'activo'));

        return response()->json([
            'message'    => 'Forma de pago actualizada.',
            'forma_pago' => $formaPago,
        ]);
    }
}
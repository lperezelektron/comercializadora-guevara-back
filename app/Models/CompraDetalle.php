<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'compras_detalle';

    protected $fillable = [
        'compra_id',
        'inventario_id',
        'cantidad',
        'empaque',
        'costo',
        'impuestos',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'empaque' => 'decimal:2',
        'costo' => 'decimal:2',
        'impuestos' => 'decimal:2',
    ];

    // Relaciones
    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function inventario()
    {
        return $this->belongsTo(Inventario::class);
    }

    // Métodos auxiliares
    public function calcularSubtotal()
    {
        return $this->cantidad * $this->costo;
    }

    public function calcularTotal()
    {
        return $this->calcularSubtotal() + $this->impuestos;
    }
}
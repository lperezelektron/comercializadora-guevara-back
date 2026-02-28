<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentaDetalle extends Model
{
    use HasFactory;

    protected $table = 'ventas_detalle';

    protected $fillable = [
        'venta_id',
        'articulo_id',
        'lote_id',
        'cantidad',
        'empaque',
        'precio',
        'impuestos',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'empaque' => 'decimal:2',
        'precio' => 'decimal:2',
        'impuestos' => 'decimal:2',
    ];

    // Relaciones
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    public function lote()
    {
        return $this->belongsTo(Inventario::class, 'lote_id');
    }

    // Métodos auxiliares
    public function calcularSubtotal()
    {
        return $this->cantidad * $this->precio;
    }

    public function calcularTotal()
    {
        return $this->calcularSubtotal() + $this->impuestos;
    }
}
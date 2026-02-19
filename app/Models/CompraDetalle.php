<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'compras_detalle';

    protected $fillable = [
        'lote',
        'compra_id',
        'articulo_id',
        'variedad',
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

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    public function ventasDetalle()
    {
        return $this->hasMany(VentaDetalle::class, 'lote_id', 'lote');
    }

    public function movimientosKardex()
    {
        return $this->hasMany(Kardex::class, 'lote_id', 'lote');
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

    public function stockDisponible()
    {
        $totalVendido = $this->ventasDetalle()->sum('cantidad');
        return $this->cantidad - $totalVendido;
    }

    public static function generarLote()
    {
        $fecha = now()->format('Ymd');
        $ultimo = self::where('lote', 'like', "LOT-{$fecha}-%")->orderBy('id', 'desc')->first();
        
        if ($ultimo) {
            $numero = intval(substr($ultimo->lote, -3)) + 1;
        } else {
            $numero = 1;
        }
        
        return "LOT-{$fecha}-" . str_pad($numero, 3, '0', STR_PAD_LEFT);
    }

    // Scopes
    public function scopeConStock($query)
    {
        return $query->whereRaw('cantidad > (SELECT COALESCE(SUM(cantidad), 0) FROM ventas_detalle WHERE lote_id = compras_detalle.lote)');
    }
}
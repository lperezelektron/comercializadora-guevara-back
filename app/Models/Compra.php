<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Compra extends Model
{
    use HasFactory;

    protected $fillable = [
        'fecha',
        'referencia',
        'proveedor_id',
        'user_id',
        'subtotal',
        'impuestos',
        'total',
    ];

    protected $casts = [
        'fecha' => 'date',
        'subtotal' => 'decimal:2',
        'impuestos' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relaciones
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class);
    }

    public function ctaPorPagar()
    {
        return $this->hasOne(CtaXPagar::class);
    }

    // Métodos auxiliares
    public function calcularTotal()
    {
        $this->total = $this->subtotal + $this->impuestos;
        return $this->total;
    }

    public function esCredito()
    {
        return $this->ctaPorPagar()->exists();
    }

    public static function generarReferencia()
    {
        $ultimaCompra = self::orderBy('id', 'desc')->first();
        $numero = $ultimaCompra ? intval(substr($ultimaCompra->referencia, 3)) + 1 : 1;
        return 'CMP' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    // Scopes
    public function scopeDelMes($query, $mes = null, $anio = null)
    {
        $mes = $mes ?? now()->month;
        $anio = $anio ?? now()->year;
        return $query->whereMonth('fecha', $mes)->whereYear('fecha', $anio);
    }

    public function scopePorProveedor($query, $proveedorId)
    {
        return $query->where('proveedor_id', $proveedorId);
    }
}
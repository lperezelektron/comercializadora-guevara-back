<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;

    protected $fillable = [
        'fecha',
        'cliente_id',
        'almacen_id',
        'user_id',
        'credito',
        'subtotal',
        'impuestos',
        'total',
        'f_pago_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'credito' => 'boolean',
        'subtotal' => 'decimal:2',
        'impuestos' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relaciones
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function formaPago()
    {
        return $this->belongsTo(FormaPago::class, 'f_pago_id');
    }

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function ctaPorCobrar()
    {
        return $this->hasOne(CtaXCobrar::class);
    }

    // Métodos auxiliares
    public function calcularTotal()
    {
        $this->total = $this->subtotal + $this->impuestos;
        return $this->total;
    }

    public function esCredito()
    {
        return $this->credito;
    }

    public function esContado()
    {
        return !$this->credito;
    }

    public static function generarNumero()
    {
        $ultimaVenta = self::orderBy('id', 'desc')->first();
        $numero = $ultimaVenta ? $ultimaVenta->id + 1 : 1;
        return 'VTA' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    // Scopes
    public function scopeDelMes($query, $mes = null, $anio = null)
    {
        $mes = $mes ?? now()->month;
        $anio = $anio ?? now()->year;
        return $query->whereMonth('fecha', $mes)->whereYear('fecha', $anio);
    }

    public function scopeCredito($query)
    {
        return $query->where('credito', true);
    }

    public function scopeContado($query)
    {
        return $query->where('credito', false);
    }

    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    public function scopePorAlmacen($query, $almacenId)
    {
        return $query->where('almacen_id', $almacenId);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CtaXPagar extends Model
{
    use HasFactory;

    protected $table = 'ctas_x_pagar';

    protected $fillable = [
        'compra_id',
        'proveedor_id',
        'fecha',
        'vencimiento',
        'importe',
        'saldo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'vencimiento' => 'date',
        'importe' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    // Relaciones
    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function detalles()
    {
        return $this->hasMany(CxpDetalle::class, 'cxp_id');
    }

    // Métodos auxiliares
    public function abonar($importe, $formaPagoId)
    {
        if ($importe > $this->saldo) {
            throw new \Exception("El abono no puede ser mayor al saldo pendiente");
        }

        CxpDetalle::create([
            'cxp_id' => $this->id,
            'fecha' => now(),
            'importe' => $importe,
            'f_pago_id' => $formaPagoId,
            'tipo' => 'abono',
        ]);

        $this->saldo -= $importe;
        $this->save();

        return $this;
    }

    public function cargo($importe, $formaPagoId)
    {
        CxpDetalle::create([
            'cxp_id' => $this->id,
            'fecha' => now(),
            'importe' => $importe,
            'f_pago_id' => $formaPagoId,
            'tipo' => 'cargo',
        ]);

        $this->saldo += $importe;
        $this->save();

        return $this;
    }

    public function estaVencida()
    {
        return $this->vencimiento < now() && $this->saldo > 0;
    }

    public function estaSaldada()
    {
        return $this->saldo == 0;
    }

    public function diasVencimiento()
    {
        if (!$this->estaVencida()) return 0;
        return now()->diffInDays($this->vencimiento);
    }

    // Scopes
    public function scopeVencidas($query)
    {
        return $query->where('vencimiento', '<', now())
                     ->where('saldo', '>', 0);
    }

    public function scopePendientes($query)
    {
        return $query->where('saldo', '>', 0);
    }

    public function scopePorProveedor($query, $proveedorId)
    {
        return $query->where('proveedor_id', $proveedorId);
    }
}
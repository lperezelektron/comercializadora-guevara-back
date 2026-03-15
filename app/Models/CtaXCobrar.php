<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CtaXCobrar extends Model
{
    use HasFactory;

    protected $table = 'ctas_x_cobrar';

    protected $fillable = [
        'fecha',
        'vencimiento',
        'cliente_id',
        'venta_id',
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
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function detalles()
    {
        return $this->hasMany(CxcDetalle::class, 'cxc_id');
    }

    // Métodos auxiliares
    public function abonar($importe, $formaPagoId)
    {
        if ($importe > $this->saldo) {
            throw new \Exception("El abono no puede ser mayor al saldo pendiente");
        }

        CxcDetalle::create([
            'cxc_id' => $this->id,
            'fecha' => now(),
            'importe' => $importe,
            'f_pago_id' => $formaPagoId,
        ]);

        $this->saldo -= $importe;
        $this->save();

        // Registrar entrada en caja si es efectivo
        $formaPago = FormaPago::find($formaPagoId);
        if ($formaPago && strtolower($formaPago->descripcion) === 'efectivo') {
            Caja::entrada(
                $importe,
                "Abono CxC #{$this->id} - Cliente: {$this->cliente->nombre}",
                $this->venta->almacen_id ?? null
            );
        }

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

    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }
}
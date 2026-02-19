<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'direccion',
        'ciudad',
        'telefono',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relaciones
    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function ctasPorCobrar()
    {
        return $this->hasMany(CtaXCobrar::class);
    }

    // Métodos auxiliares
    public function isActive()
    {
        return $this->activo;
    }

    public function totalVentas()
    {
        return $this->ventas()->sum('total');
    }

    public function saldoPendiente()
    {
        return $this->ctasPorCobrar()->sum('saldo');
    }

    public function ultimaVenta()
    {
        return $this->ventas()->latest('fecha')->first();
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeConSaldo($query)
    {
        return $query->whereHas('ctasPorCobrar', function($q) {
            $q->where('saldo', '>', 0);
        });
    }
}
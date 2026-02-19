<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'direccion',
        'ciudad',
        'rfc',
        'telefono',
        'dias_credito',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'dias_credito' => 'integer',
    ];

    // Relaciones
    public function compras()
    {
        return $this->hasMany(Compra::class);
    }

    public function ctasPorPagar()
    {
        return $this->hasMany(CtaXPagar::class);
    }

    // Métodos auxiliares
    public function isActive()
    {
        return $this->activo;
    }

    public function totalCompras()
    {
        return $this->compras()->sum('total');
    }

    public function saldoPendiente()
    {
        return $this->ctasPorPagar()->sum('saldo');
    }

    public function tieneCredito()
    {
        return $this->dias_credito > 0;
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeConCredito($query)
    {
        return $query->where('dias_credito', '>', 0);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormaPago extends Model
{
    use HasFactory;

    protected $table = 'forma_pago';

    protected $fillable = [
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relaciones
    public function ventas()
    {
        return $this->hasMany(Venta::class, 'f_pago_id');
    }

    public function cxcDetalles()
    {
        return $this->hasMany(CxcDetalle::class, 'f_pago_id');
    }

    public function cxpDetalles()
    {
        return $this->hasMany(CxpDetalle::class, 'f_pago_id');
    }

    // Métodos auxiliares
    public function isActive()
    {
        return $this->activo;
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}
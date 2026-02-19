<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    use HasFactory;

    protected $table = 'almacenes';

    protected $fillable = [
        'descripcion',
        'direccion',
        'ciudad',
        'telefono',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relaciones
    public function inventarios()
    {
        return $this->hasMany(Inventario::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    // Métodos auxiliares
    public function isActive()
    {
        return $this->activo;
    }

    public function valorInventario()
    {
        return $this->inventarios()
                    ->selectRaw('SUM(existencia * costo) as total')
                    ->value('total') ?? 0;
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}
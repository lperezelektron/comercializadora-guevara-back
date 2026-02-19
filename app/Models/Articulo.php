<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Articulo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'nombre_corto',
        'unidad',
        'categoria_id',
        'activo',
        'imagen',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relaciones
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function inventarios()
    {
        return $this->hasMany(Inventario::class);
    }

    public function comprasDetalle()
    {
        return $this->hasMany(CompraDetalle::class);
    }

    public function ventasDetalle()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    // Métodos auxiliares
    public function isActive()
    {
        return $this->activo;
    }

    public function stockTotal()
    {
        return $this->inventarios()->sum('existencia');
    }

    public function stockEnAlmacen($almacenId)
    {
        return $this->inventarios()
                    ->where('almacen_id', $almacenId)
                    ->sum('existencia');
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorCategoria($query, $categoriaId)
    {
        return $query->where('categoria_id', $categoriaId);
    }
}
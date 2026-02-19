<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    use HasFactory;

    protected $table = 'inventario';

    protected $fillable = [
        'almacen_id',
        'articulo_id',
        'variedad',
        'existencia',
        'precio',
        'precio_min',
        'costo',
        'empaque',
    ];

    protected $casts = [
        'existencia' => 'decimal:2',
        'precio' => 'decimal:2',
        'precio_min' => 'decimal:2',
        'costo' => 'decimal:2',
        'empaque' => 'decimal:2',
    ];

    // Relaciones
    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    public function comprasDetalle()
    {
        return $this->hasMany(CompraDetalle::class);
    }

    public function ventasDetalle()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function movimientosKardex()
    {
        return $this->hasMany(Kardex::class);
    }

    // Métodos auxiliares
    public function hasStock($cantidad)
    {
        return $this->existencia >= $cantidad;
    }

    public function increaseStock($cantidad)
    {
        $this->existencia += $cantidad;
        $this->save();
    }

    public function decreaseStock($cantidad)
    {
        if (!$this->hasStock($cantidad)) {
            throw new \Exception("Stock insuficiente. Disponible: {$this->existencia}");
        }
        
        $this->existencia -= $cantidad;
        $this->save();
    }

    public function valorTotal()
    {
        return $this->existencia * $this->costo;
    }

    public function margenGanancia()
    {
        if ($this->costo == 0) return 0;
        return (($this->precio - $this->costo) / $this->costo) * 100;
    }

    // Scopes
    public function scopeStockBajo($query)
    {
        return $query->where('existencia', '<=', 10); // Ajustar según necesidad
    }

    public function scopePorAlmacen($query, $almacenId)
    {
        return $query->where('almacen_id', $almacenId);
    }

    public function scopePorArticulo($query, $articuloId)
    {
        return $query->where('articulo_id', $articuloId);
    }
}
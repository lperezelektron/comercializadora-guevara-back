<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'imagen',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $appends = ['imagen_url'];

    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen
            ? Storage::disk('public')->url($this->imagen)
            : null;
    }

    // Relaciones
    public function inventarios()
    {
        return $this->hasMany(Inventario::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function usuarios()
    {
        return $this->hasMany(User::class);
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
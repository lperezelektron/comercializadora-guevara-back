<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use HasFactory;

    protected $fillable = [
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relaciones
    public function articulos()
    {
        return $this->hasMany(Articulo::class, 'categoria_id');
    }

    // Métodos auxiliares
    public function articulosActivos()
    {
        return $this->articulos()->where('activo', true);
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}
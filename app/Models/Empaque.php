<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empaque extends Model
{
    use HasFactory;

    protected $fillable = [
        'descripcion',
        'dimensiones',
        'peso',
        'existencias',
        'activo',
    ];

    protected $casts = [
        'activo'      => 'boolean',
        'existencias' => 'float',
        'peso'        => 'float',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────

    public function saldos()
    {
        return $this->hasMany(EmpaqueClienteSaldo::class);
    }

    public function movimientos()
    {
        return $this->hasMany(EmpaqueMovimiento::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Saldo total prestado (suma de lo que tienen todos los clientes). */
    public function totalPrestado(): float
    {
        return (float) $this->saldos()->where('saldo', '>', 0)->sum('saldo');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}

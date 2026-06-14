<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpaqueMovimiento extends Model
{
    use HasFactory;

    protected $table = 'empaque_movimientos';

    protected $fillable = [
        'folio',
        'fecha',
        'empaque_id',
        'cliente_id',
        'tipo',
        'cantidad',
        'notas',
        'user_id',
    ];

    protected $casts = [
        'fecha'    => 'date',
        'cantidad' => 'float',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────

    public function empaque()
    {
        return $this->belongsTo(Empaque::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeSalidas($query)
    {
        return $query->where('tipo', 'salida');
    }

    public function scopeEntradas($query)
    {
        return $query->where('tipo', 'entrada');
    }
}

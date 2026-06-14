<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpaqueClienteSaldo extends Model
{
    use HasFactory;

    protected $table = 'empaque_cliente_saldos';

    protected $fillable = [
        'empaque_id',
        'cliente_id',
        'saldo',
    ];

    protected $casts = [
        'saldo' => 'float',
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CxpDetalle extends Model
{
    use HasFactory;

    protected $table = 'cxp_detalle';

    protected $fillable = [
        'cxp_id',
        'fecha',
        'importe',
        'f_pago_id',
        'tipo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
    ];

    // Relaciones
    public function ctaXPagar()
    {
        return $this->belongsTo(CtaXPagar::class, 'cxp_id');
    }

    public function formaPago()
    {
        return $this->belongsTo(FormaPago::class, 'f_pago_id');
    }

    // Métodos auxiliares
    public function esAbono()
    {
        return $this->tipo === 'abono';
    }

    public function esCargo()
    {
        return $this->tipo === 'cargo';
    }
}
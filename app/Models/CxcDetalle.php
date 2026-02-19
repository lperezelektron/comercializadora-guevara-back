<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CxcDetalle extends Model
{
    use HasFactory;

    protected $table = 'cxc_detalle';

    protected $fillable = [
        'cxc_id',
        'fecha',
        'importe',
        'f_pago_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
    ];

    // Relaciones
    public function ctaXCobrar()
    {
        return $this->belongsTo(CtaXCobrar::class, 'cxc_id');
    }

    public function formaPago()
    {
        return $this->belongsTo(FormaPago::class, 'f_pago_id');
    }
}
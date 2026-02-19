<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorteCaja extends Model
{
    use HasFactory;

    protected $table = 'corte_caja';

    protected $fillable = [
        'fecha',
        'importe',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function movimientos()
    {
        return $this->hasMany(Caja::class, 'corte_id');
    }

    // Métodos auxiliares
    public function calcularImporte()
    {
        $entradas = $this->movimientos()->where('tipo', 'entrada')->sum('cantidad');
        $salidas = $this->movimientos()->where('tipo', 'salida')->sum('cantidad');
        
        $this->importe = $entradas - $salidas;
        return $this->importe;
    }

    public function cerrar()
    {
        $this->calcularImporte();
        $this->save();

        // Asociar todos los movimientos sin corte a este corte
        Caja::whereNull('corte_id')
            ->where('fecha', '<=', $this->fecha)
            ->update(['corte_id' => $this->id]);

        return $this;
    }

    // Scopes
    public function scopeDelMes($query, $mes = null, $anio = null)
    {
        $mes = $mes ?? now()->month;
        $anio = $anio ?? now()->year;
        return $query->whereMonth('fecha', $mes)->whereYear('fecha', $anio);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kardex extends Model
{
    use HasFactory;

    protected $table = 'kardex';

    protected $fillable = [
        'lote_id',
        'fecha',
        'movimiento',
        'tipo',
        'documento',
        'cte_prv',
        'cantidad',
        'empaque',
        'costo',
        'precio',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad' => 'decimal:2',
        'empaque' => 'decimal:2',
        'costo' => 'decimal:2',
        'precio' => 'decimal:2',
    ];

    // Relaciones
    public function lote()
    {
        return $this->belongsTo(CompraDetalle::class, 'lote_id', 'lote');
    }

    // Métodos auxiliares
    public function esEntrada()
    {
        return $this->tipo === 'entrada';
    }

    public function esSalida()
    {
        return $this->tipo === 'salida';
    }

    public function valorTotal()
    {
        return $this->cantidad * $this->costo;
    }

    public static function registrarEntrada($data)
    {
        return self::create([
            'lote_id' => $data['lote_id'],
            'fecha' => $data['fecha'] ?? now(),
            'movimiento' => $data['movimiento'] ?? 'Compra',
            'tipo' => 'entrada',
            'documento' => $data['documento'],
            'cte_prv' => $data['proveedor_id'],
            'cantidad' => $data['cantidad'],
            'empaque' => $data['empaque'] ?? 0,
            'costo' => $data['costo'],
            'precio' => $data['precio'] ?? 0,
        ]);
    }

    public static function registrarSalida($data)
    {
        return self::create([
            'lote_id' => $data['lote_id'],
            'fecha' => $data['fecha'] ?? now(),
            'movimiento' => $data['movimiento'] ?? 'Venta',
            'tipo' => 'salida',
            'documento' => $data['documento'],
            'cte_prv' => $data['cliente_id'],
            'cantidad' => $data['cantidad'],
            'empaque' => $data['empaque'] ?? 0,
            'costo' => $data['costo'],
            'precio' => $data['precio'],
        ]);
    }

    // Scopes
    public function scopeEntradas($query)
    {
        return $query->where('tipo', 'entrada');
    }

    public function scopeSalidas($query)
    {
        return $query->where('tipo', 'salida');
    }

    public function scopePorLote($query, $loteId)
    {
        return $query->where('lote_id', $loteId);
    }

    public function scopePorFecha($query, $fechaInicio, $fechaFin = null)
    {
        if ($fechaFin) {
            return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
        }
        return $query->whereDate('fecha', $fechaInicio);
    }

    public function scopeDelMes($query, $mes = null, $anio = null)
    {
        $mes = $mes ?? now()->month;
        $anio = $anio ?? now()->year;
        return $query->whereMonth('fecha', $mes)->whereYear('fecha', $anio);
    }
}
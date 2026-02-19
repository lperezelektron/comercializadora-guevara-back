<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    use HasFactory;

    protected $table = 'caja';

    protected $fillable = [
        'fecha',
        'tipo',
        'cantidad',
        'referencia',
        'corte_id',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad' => 'decimal:2',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function corte()
    {
        return $this->belongsTo(CorteCaja::class, 'corte_id');
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

    public function tieneCorteCerrado()
    {
        return $this->corte_id !== null;
    }

    public static function entrada($cantidad, $referencia)
    {
        return self::create([
            'fecha' => now(),
            'tipo' => 'entrada',
            'cantidad' => $cantidad,
            'referencia' => $referencia,
            'user_id' => auth()->id(),
        ]);
    }

    public static function salida($cantidad, $referencia)
    {
        return self::create([
            'fecha' => now(),
            'tipo' => 'salida',
            'cantidad' => $cantidad,
            'referencia' => $referencia,
            'user_id' => auth()->id(),
        ]);
    }

    public static function saldoActual()
    {
        $entradas = self::where('tipo', 'entrada')->sum('cantidad');
        $salidas = self::where('tipo', 'salida')->sum('cantidad');
        return $entradas - $salidas;
    }

    public static function saldoDelDia($fecha = null)
    {
        $fecha = $fecha ?? now()->toDateString();
        
        $entradas = self::where('tipo', 'entrada')
                        ->whereDate('fecha', $fecha)
                        ->sum('cantidad');
        
        $salidas = self::where('tipo', 'salida')
                       ->whereDate('fecha', $fecha)
                       ->sum('cantidad');
        
        return $entradas - $salidas;
    }

    public static function movimientosSinCerrar()
    {
        return self::whereNull('corte_id')->get();
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

    public function scopeSinCerrar($query)
    {
        return $query->whereNull('corte_id');
    }

    public function scopeDelDia($query, $fecha = null)
    {
        $fecha = $fecha ?? now()->toDateString();
        return $query->whereDate('fecha', $fecha);
    }

    public function scopeDelMes($query, $mes = null, $anio = null)
    {
        $mes = $mes ?? now()->month;
        $anio = $anio ?? now()->year;
        return $query->whereMonth('fecha', $mes)->whereYear('fecha', $anio);
    }

    public function scopePorUsuario($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
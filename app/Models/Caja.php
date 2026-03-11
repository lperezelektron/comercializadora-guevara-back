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
        'almacen_id',
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

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
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

    public static function entrada($cantidad, $referencia, $almacenId = null)
    {
        return self::create([
            'fecha'       => now(),
            'tipo'        => 'entrada',
            'cantidad'    => $cantidad,
            'referencia'  => $referencia,
            'user_id'     => auth()->id(),
            'almacen_id'  => $almacenId,
        ]);
    }

    public static function salida($cantidad, $referencia, $almacenId = null)
    {
        return self::create([
            'fecha'       => now(),
            'tipo'        => 'salida',
            'cantidad'    => $cantidad,
            'referencia'  => $referencia,
            'user_id'     => auth()->id(),
            'almacen_id'  => $almacenId,
        ]);
    }

    public static function saldoActual($almacenId = null)
    {
        $q = self::whereNull('corte_id');
        if ($almacenId) $q->where('almacen_id', $almacenId);

        $entradas = (clone $q)->where('tipo', 'entrada')->sum('cantidad');
        $salidas  = (clone $q)->where('tipo', 'salida')->sum('cantidad');
        return $entradas - $salidas;
    }

    public static function saldoDelDia($fecha = null, $almacenId = null)
    {
        $fecha = $fecha ?? now()->toDateString();

        $q = self::whereNull('corte_id')->whereDate('fecha', $fecha);
        if ($almacenId) $q->where('almacen_id', $almacenId);

        $entradas = (clone $q)->where('tipo', 'entrada')->sum('cantidad');
        $salidas  = (clone $q)->where('tipo', 'salida')->sum('cantidad');

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

    public function scopePorAlmacen($query, $almacenId)
    {
        return $query->where('almacen_id', $almacenId);
    }
}
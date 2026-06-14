<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Empaque;
use App\Models\EmpaqueClienteSaldo;
use App\Models\EmpaqueMovimiento;
use App\Services\TicketEscPos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmpaqueMovimientoController extends Controller
{
    public function index(Request $request)
    {
        $query = EmpaqueMovimiento::with(['empaque', 'cliente', 'user'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('empaque_id')) {
            $query->where('empaque_id', $request->empaque_id);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha', '<=', $request->fecha_fin);
        }

        return response()->json(
            $request->filled('per_page')
                ? $query->paginate($request->per_page)
                : $query->get()
        );
    }

    /**
     * Registrar un movimiento de empaque.
     *
     * tipo = salida → se prestan empaques al cliente
     *   · existencias del empaque disminuyen
     *   · saldo del cliente aumenta (cliente debe más empaques)
     *
     * tipo = entrada → el cliente devuelve empaques
     *   · existencias del empaque aumentan
     *   · saldo del cliente disminuye
     */
    public function store(Request $request)
    {
        $request->validate([
            'fecha'      => 'required|date',
            'empaque_id' => 'required|exists:empaques,id',
            'cliente_id' => 'required|exists:clientes,id',
            'tipo'       => 'required|in:salida,entrada',
            'cantidad'   => 'required|numeric|min:0.01',
            'notas'      => 'nullable|string|max:500',
        ]);

        $empaque = Empaque::findOrFail($request->empaque_id);

        // Validar existencias suficientes en salida
        if ($request->tipo === 'salida' && $empaque->existencias < $request->cantidad) {
            return response()->json([
                'message' => "Existencias insuficientes. Disponibles: {$empaque->existencias}",
            ], 422);
        }

        DB::beginTransaction();

        try {
            // 1. Crear el movimiento
            $movimiento = EmpaqueMovimiento::create([
                'folio'      => '',              // se llena después del insert
                'fecha'      => $request->fecha,
                'empaque_id' => $request->empaque_id,
                'cliente_id' => $request->cliente_id,
                'tipo'       => $request->tipo,
                'cantidad'   => $request->cantidad,
                'notas'      => $request->notas,
                'user_id'    => auth()->id(),
            ]);

            // Asignar folio con el ID generado
            $movimiento->folio = 'EMP' . str_pad($movimiento->id, 6, '0', STR_PAD_LEFT);
            $movimiento->save();

            // 2. Ajustar existencias del empaque
            if ($request->tipo === 'salida') {
                $empaque->decrement('existencias', $request->cantidad);
            } else {
                $empaque->increment('existencias', $request->cantidad);
            }

            // 3. Actualizar o crear el saldo del cliente para este empaque
            $saldo = EmpaqueClienteSaldo::firstOrCreate(
                ['empaque_id' => $request->empaque_id, 'cliente_id' => $request->cliente_id],
                ['saldo' => 0]
            );

            if ($request->tipo === 'salida') {
                $saldo->increment('saldo', $request->cantidad);
            } else {
                $saldo->decrement('saldo', $request->cantidad);
            }

            DB::commit();

            return response()->json([
                'message'     => 'Movimiento registrado correctamente.',
                'movimiento'  => $movimiento->load(['empaque', 'cliente', 'user']),
                'existencias' => $empaque->fresh()->existencias,
                'saldo'       => $saldo->fresh()->saldo,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar el movimiento.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show(EmpaqueMovimiento $empaqueMovimiento)
    {
        return response()->json(
            $empaqueMovimiento->load(['empaque', 'cliente', 'user'])
        );
    }

    /**
     * Generar ticket ESC/POS para impresora térmica.
     * GET /empaque-movimientos/{empaqueMovimiento}/ticket?cols=48
     */
    public function ticket(Request $request, EmpaqueMovimiento $empaqueMovimiento)
    {
        $empaqueMovimiento->load(['empaque', 'cliente', 'user']);

        $cols    = (int) $request->get('cols', 42);
        $ticket  = new TicketEscPos($cols);
        $almacen = Almacen::where('activo', true)->first();

        // ── Encabezado (logo o texto) ─────────────────────────────────────
        $ticket->doubleLine();

        if ($almacen) {
            $textLines = array_values(array_filter([
                $almacen->descripcion,
                $almacen->direccion ?: null,
                trim(($almacen->ciudad ?? '') . ($almacen->telefono ? '  Tel: ' . $almacen->telefono : '')) ?: null,
            ]));

            $printTextHeader = function () use ($ticket, $almacen) {
                $ticket->bigCenter($almacen->descripcion)->feed();
                if ($almacen->direccion) {
                    $ticket->center($almacen->direccion);
                }
                $ciudadTel = trim(($almacen->ciudad ?? '') . ($almacen->telefono ? '  Tel: ' . $almacen->telefono : ''));
                if ($ciudadTel) {
                    $ticket->center($ciudadTel);
                }
            };

            if ($almacen->imagen) {
                try {
                    $logoData = Storage::disk('public')->get($almacen->imagen);
                    $ticket->addLogoHeader($logoData, $textLines);
                } catch (\Throwable) {
                    $printTextHeader();
                }
            } else {
                $printTextHeader();
            }
        }

        // ── Datos del movimiento ──────────────────────────────────────────
        $tipoLabel = $empaqueMovimiento->tipo === 'salida'
            ? 'SALIDA (PRÉSTAMO)'
            : 'ENTRADA (DEVOLUCIÓN)';

        $saldo = EmpaqueClienteSaldo::where([
            'empaque_id' => $empaqueMovimiento->empaque_id,
            'cliente_id' => $empaqueMovimiento->cliente_id,
        ])->value('saldo') ?? 0;

        $fecha = \Carbon\Carbon::parse($empaqueMovimiento->fecha)->format('d/m/Y');

        $ticket->doubleLine()
               ->center('MOVIMIENTO DE EMPAQUE', true)
               ->doubleLine()
               ->row('FOLIO:', $empaqueMovimiento->folio)
               ->row('FECHA:', $fecha)
               ->left('CLIENTE: ' . mb_strtoupper($empaqueMovimiento->cliente->nombre))
               ->line()
               ->row('EMPAQUE:', mb_strtoupper($empaqueMovimiento->empaque->descripcion));

        if ($empaqueMovimiento->empaque->dimensiones) {
            $ticket->left('  ' . $empaqueMovimiento->empaque->dimensiones);
        }

        $ticket->row('TIPO:', $tipoLabel)
               ->row('CANTIDAD:', (string) (int) $empaqueMovimiento->cantidad);

        if ($empaqueMovimiento->notas) {
            $ticket->left('NOTAS: ' . $empaqueMovimiento->notas);
        }

        $ticket->line()
               ->row('SALDO CLIENTE:', (string) (int) $saldo . ' empaque(s)')
               ->doubleLine()
               ->center('Gracias por su preferencia.')
               ->feed(2)
               ->cut();

        return response($ticket->get(), 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $empaqueMovimiento->folio . '.bin"',
        ]);
    }
}

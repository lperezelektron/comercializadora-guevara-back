<?php
// routes/api.php — VERSIÓN COMPLETA CON USUARIOS, ROLES Y PERMISOS

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\ArticuloController;
use App\Http\Controllers\Api\AlmacenController;
use App\Http\Controllers\Api\ProveedorController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\FormaPagoController;
use App\Http\Controllers\Api\CompraController;
use App\Http\Controllers\Api\VentaController;
use App\Http\Controllers\Api\CxcController;
use App\Http\Controllers\Api\CxpController;
use App\Http\Controllers\Api\CajaController;
use App\Http\Controllers\Api\KardexController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\EmpleadoController;
use App\Http\Controllers\Api\EmpaqueController;
use App\Http\Controllers\Api\EmpaqueMovimientoController;

// ─────────────────────────────────────────────
//  Pública
// ─────────────────────────────────────────────
Route::post('auth/login', [AuthController::class, 'login']);

// ─────────────────────────────────────────────
//  Protegidas con Sanctum
// ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ──────────────────────────────────────────────────
    Route::post('auth/logout',           [AuthController::class, 'logout']);
    Route::get('auth/perfil',            [AuthController::class, 'perfil']);
    Route::post('auth/cambiar-password', [AuthController::class, 'cambiarPassword']);

    // ── Usuarios ──────────────────────────────────────────────
    Route::get('usuarios',                          [UsuarioController::class, 'index']);
    Route::post('usuarios',                         [UsuarioController::class, 'store']);
    Route::get('usuarios/{usuario}',                [UsuarioController::class, 'show']);
    Route::put('usuarios/{usuario}',                [UsuarioController::class, 'update']);
    Route::delete('usuarios/{usuario}',             [UsuarioController::class, 'destroy']);
    Route::post('usuarios/{usuario}/reset-password',[UsuarioController::class, 'resetPassword']);
    Route::patch('usuarios/{usuario}/toggle-status',[UsuarioController::class, 'toggleStatus']);

    // ── Roles ─────────────────────────────────────────────────
    Route::get('roles',               [RoleController::class, 'index']);
    Route::post('roles',              [RoleController::class, 'store']);
    Route::get('roles/{role}',        [RoleController::class, 'show']);
    Route::put('roles/{role}',        [RoleController::class, 'update']);
    Route::delete('roles/{role}',     [RoleController::class, 'destroy']);
    Route::post('roles/{role}/permisos/asignar', [RoleController::class, 'asignarPermiso']);
    Route::post('roles/{role}/permisos/revocar', [RoleController::class, 'revocarPermiso']);

    // ── Permisos ──────────────────────────────────────────────
    Route::get('permisos',               [PermissionController::class, 'index']);
    Route::post('permisos',              [PermissionController::class, 'store']);
    Route::get('permisos/{permission}',  [PermissionController::class, 'show']);
    Route::put('permisos/{permission}',  [PermissionController::class, 'update']);
    Route::delete('permisos/{permission}',[PermissionController::class, 'destroy']);

    // ── Empleados ─────────────────────────────────────────────
    Route::apiResource('empleados', EmpleadoController::class);

    // ── Catálogos ─────────────────────────────────────────────
    Route::apiResource('categorias',  CategoriaController::class);
    Route::post('articulos/reordenar',          [ArticuloController::class, 'reordenar']);
    Route::get('articulos/con-existencia',      [ArticuloController::class, 'conExistencia']);
    Route::post('articulos/importar', [ArticuloController::class, 'importar']);
    Route::apiResource('articulos',             ArticuloController::class);
    Route::get('articulos/{articulo}/stock', [ArticuloController::class, 'stock']);

    Route::apiResource('almacenes', AlmacenController::class)
    ->parameters(['almacenes' => 'almacen']);
    Route::get('almacenes/{almacen}/inventario', [AlmacenController::class, 'inventario']);

    Route::apiResource('proveedores', ProveedorController::class)
    ->parameters(['proveedores' => 'proveedor']);
    Route::get('proveedores/{proveedor}/estado-cuenta', [ProveedorController::class, 'estadoCuenta']);

    Route::apiResource('clientes',    ClienteController::class);
    Route::get('clientes/{cliente}/estado-cuenta', [ClienteController::class, 'estadoCuenta']);

    Route::get('formas-pago',                [FormaPagoController::class, 'index']);
    Route::post('formas-pago',               [FormaPagoController::class, 'store']);
    Route::put('formas-pago/{formaPago}',    [FormaPagoController::class, 'update']);

    // ── Compras ───────────────────────────────────────────────
    Route::get('compras',                    [CompraController::class, 'index']);
    Route::post('compras',                   [CompraController::class, 'store']);
    Route::get('compras/{compra}',           [CompraController::class, 'show']);
    Route::post('compras/{compra}/cancelar', [CompraController::class, 'cancelar']);

    // ── Ventas ────────────────────────────────────────────────
    // IMPORTANTE: ruta estática ANTES del resource para evitar conflicto con {venta}
    Route::get('ventas/lotes-disponibles',   [VentaController::class, 'lotesDisponibles']);
    Route::get('ventas/reportes/por-articulo', [VentaController::class, 'resumenPorArticulo']);
    Route::get('ventas/reportes/formas-pago',  [VentaController::class, 'resumenFormasPago']);
    Route::get('ventas/reportes/diario',       [VentaController::class, 'resumenDiario']);
    Route::get('ventas',                     [VentaController::class, 'index']);
    Route::post('ventas',                    [VentaController::class, 'store']);
    Route::get('ventas/{venta}',             [VentaController::class, 'show']);
    Route::get('ventas/{venta}/ticket',      [VentaController::class, 'ticket']);
    Route::post('ventas/{venta}/cancelar',   [VentaController::class, 'cancelar']);

    // ── CxC ───────────────────────────────────────────────────
    Route::get('cxc',                              [CxcController::class, 'index']);
    Route::get('cxc/resumen',                      [CxcController::class, 'resumen']);
    Route::get('cxc/{ctaXCobrar}',                 [CxcController::class, 'show']);
    Route::post('cxc/{ctaXCobrar}/abonar',         [CxcController::class, 'abonar']);

    // ── CxP ───────────────────────────────────────────────────
    Route::get('cxp',                              [CxpController::class, 'index']);
    Route::get('cxp/resumen',                      [CxpController::class, 'resumen']);
    Route::get('cxp/{ctaXPagar}',                  [CxpController::class, 'show']);
    Route::post('cxp/{ctaXPagar}/pagar',           [CxpController::class, 'pagar']);

    // ── Caja ──────────────────────────────────────────────────
    // IMPORTANTE: rutas estáticas ANTES de las dinámicas
    Route::get('caja/cortes',                      [CajaController::class, 'cortes']);
    Route::get('caja/cortes/{corteCaja}',          [CajaController::class, 'showCorte']);
    Route::get('caja',                             [CajaController::class, 'index']);
    Route::post('caja/movimiento',                 [CajaController::class, 'movimiento']);
    Route::post('caja/corte',                      [CajaController::class, 'corte']);

    // ── Inventario ────────────────────────────────────────────
    Route::get('inventario',                                    [InventarioController::class, 'index']);
    Route::get('inventario/{inventario}',                       [InventarioController::class, 'show']);
    Route::patch('inventario/{inventario}/precios',             [InventarioController::class, 'updatePrecios']);
    Route::post('inventario/precios-masivo',                    [InventarioController::class, 'updatePreciosMasivo']);

    // ── Kardex ────────────────────────────────────────────────
    Route::get('kardex/lote',                      [KardexController::class, 'porLote']);
    Route::get('kardex/articulo',                  [KardexController::class, 'porArticulo']);
    Route::post('kardex/ajuste',                   [KardexController::class, 'ajuste']);

    // ── Empaques ──────────────────────────────────────────────────
    // IMPORTANTE: ruta estática /empaques/saldos ANTES del resource
    Route::get('empaques/saldos',                         [EmpaqueController::class, 'saldos']);
    Route::apiResource('empaques', EmpaqueController::class);

    Route::get('empaque-movimientos',                              [EmpaqueMovimientoController::class, 'index']);
    Route::post('empaque-movimientos',                             [EmpaqueMovimientoController::class, 'store']);
    Route::get('empaque-movimientos/{empaqueMovimiento}',          [EmpaqueMovimientoController::class, 'show']);
    Route::get('empaque-movimientos/{empaqueMovimiento}/ticket',   [EmpaqueMovimientoController::class, 'ticket']);

    // ── Reportes ──────────────────────────────────────────────
    Route::prefix('reportes')->group(function () {
        Route::get('dashboard',                    [ReporteController::class, 'dashboard']);
        Route::get('ventas',                       [ReporteController::class, 'ventas']);
        Route::get('top-articulos',                [ReporteController::class, 'topArticulos']);
        Route::get('inventario',                   [ReporteController::class, 'inventarioValorizado']);
        Route::get('utilidad',                     [ReporteController::class, 'utilidad']);
        Route::get('estado-resultados',            [ReporteController::class, 'estadoResultados']);
    });
});
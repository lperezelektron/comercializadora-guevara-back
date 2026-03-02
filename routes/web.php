<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Admin\PermissionController;

Route::get('/', function () {
    return view('welcome');
});

// ─────────────────────────────────────────────
//  Panel de Administración
// ─────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {

    // Autenticación web
    Route::get('login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Rutas protegidas
    Route::middleware('auth')->group(function () {

        Route::get('/', fn () => redirect()->route('admin.roles.index'));

        // Roles
        Route::get('roles',                      [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create',               [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles',                     [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}',               [RoleController::class, 'show'])->name('roles.show');
        Route::get('roles/{role}/edit',          [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}',               [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}',            [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('roles/{role}/permisos',     [RoleController::class, 'syncPermisos'])->name('roles.permisos');

        // Usuarios
        Route::get('usuarios',                          [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('usuarios/create',                   [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('usuarios',                         [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('usuarios/{usuario}',                [UsuarioController::class, 'show'])->name('usuarios.show');
        Route::get('usuarios/{usuario}/edit',           [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('usuarios/{usuario}',                [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::delete('usuarios/{usuario}',             [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
        Route::patch('usuarios/{usuario}/toggle-status',[UsuarioController::class, 'toggleStatus'])->name('usuarios.toggle');
        Route::post('usuarios/{usuario}/reset-password',[UsuarioController::class, 'resetPassword'])->name('usuarios.reset-password');

        // Permisos
        Route::get('permisos',                    [PermissionController::class, 'index'])->name('permisos.index');
        Route::get('permisos/create',             [PermissionController::class, 'create'])->name('permisos.create');
        Route::post('permisos',                   [PermissionController::class, 'store'])->name('permisos.store');
        Route::get('permisos/{permission}/edit',  [PermissionController::class, 'edit'])->name('permisos.edit');
        Route::put('permisos/{permission}',       [PermissionController::class, 'update'])->name('permisos.update');
        Route::delete('permisos/{permission}',    [PermissionController::class, 'destroy'])->name('permisos.destroy');
    });
});

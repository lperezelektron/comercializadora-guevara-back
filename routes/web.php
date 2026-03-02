<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\RoleController;

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
    });
});

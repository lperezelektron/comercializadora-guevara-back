<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('role.permissions')
            ->where('email', $request->email)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        if (!$user->isActive()) {
            return response()->json([
                'message' => 'Tu cuenta está desactivada. Contacta al administrador.',
            ], 403);
        }

        // Sesión única: revocar tokens anteriores
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        $permisos = $user->role
            ? $user->role->permissions->pluck('name')
            : collect();

        return response()->json([
            'message' => 'Login exitoso.',
            'token'   => $token,
            'user'    => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'almacen_id'=> $user->almacen_id,
                'role'      => $user->role?->name,                
                'permisos'  => $permisos,
            ],
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    /**
     * Perfil del usuario autenticado
     */
    public function perfil(Request $request)
    {
        $user = $request->user()->load('role.permissions');

        $permisos = $user->role
            ? $user->role->permissions->pluck('name')
            : collect();

        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'telefono'   => $user->telefono,
            'status'     => $user->status,
            'almacen_id' => $user->almacen_id,
            'role'       => $user->role?->name,
            'permisos'   => $permisos,
        ]);
    }

    /**
     * Cambiar contraseña propia
     */
    public function cambiarPassword(Request $request)
    {
        $request->validate([
            'password_actual' => 'required|string',
            'password_nuevo'  => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password_actual, $user->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
        }

        $user->update(['password' => Hash::make($request->password_nuevo)]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Contraseña actualizada. Inicia sesión de nuevo.']);
    }
}
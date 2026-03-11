<?php

/**
 * Manual Verification Script for User-Almacen Assignment
 * 
 * This script demonstrates that the User-Almacen assignment functionality
 * is working correctly. Run this with: php artisan tinker < tests/Manual/VerifyUserAlmacenFunctionality.php
 * 
 * Or copy and paste into tinker manually.
 */

// Create a test almacen
$almacen = \App\Models\Almacen::create([
    'descripcion' => 'Almacen de Prueba',
    'direccion' => 'Calle Principal 123',
    'activo' => true,
]);

echo "✓ Almacen creado: {$almacen->descripcion} (ID: {$almacen->id})\n";

// Create a user with almacen assigned
$user1 = \App\Models\User::create([
    'name' => 'Usuario con Almacen',
    'email' => 'usuario1@test.com',
    'password' => bcrypt('password'),
    'status' => 'active',
    'almacen_id' => $almacen->id,
]);

echo "✓ Usuario creado con almacén: {$user1->name}\n";
echo "  - hasAlmacen(): " . ($user1->hasAlmacen() ? 'true' : 'false') . "\n";
echo "  - getAlmacenId(): {$user1->getAlmacenId()}\n";
echo "  - almacen->descripcion: {$user1->almacen->descripcion}\n";

// Create a user without almacen
$user2 = \App\Models\User::create([
    'name' => 'Usuario sin Almacen',
    'email' => 'usuario2@test.com',
    'password' => bcrypt('password'),
    'status' => 'active',
]);

echo "\n✓ Usuario creado sin almacén: {$user2->name}\n";
echo "  - hasAlmacen(): " . ($user2->hasAlmacen() ? 'true' : 'false') . "\n";
echo "  - getAlmacenId(): " . ($user2->getAlmacenId() ?? 'null') . "\n";

// Test scopes
$usuariosConAlmacen = \App\Models\User::porAlmacen($almacen->id)->get();
echo "\n✓ Usuarios en almacén {$almacen->id}: {$usuariosConAlmacen->count()}\n";

$usuariosSinAlmacen = \App\Models\User::sinAlmacen()->get();
echo "✓ Usuarios sin almacén: {$usuariosSinAlmacen->count()}\n";

// Test almacen->usuarios relationship
$usuariosDelAlmacen = $almacen->usuarios;
echo "\n✓ Usuarios asignados al almacén '{$almacen->descripcion}': {$usuariosDelAlmacen->count()}\n";
foreach ($usuariosDelAlmacen as $u) {
    echo "  - {$u->name}\n";
}

// Test ON DELETE SET NULL
echo "\n✓ Eliminando almacén...\n";
$almacen->delete();

$user1->refresh();
echo "✓ Usuario {$user1->name} después de eliminar almacén:\n";
echo "  - almacen_id: " . ($user1->almacen_id ?? 'null') . "\n";
echo "  - hasAlmacen(): " . ($user1->hasAlmacen() ? 'true' : 'false') . "\n";

// Cleanup
$user1->delete();
$user2->delete();

echo "\n✅ Todas las verificaciones completadas exitosamente!\n";

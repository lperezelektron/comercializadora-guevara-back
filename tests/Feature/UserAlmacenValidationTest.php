<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Almacen;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAlmacenValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a role for testing
        $this->role = Role::create([
            'name' => 'admin',
            'descripcion' => 'Administrator Role',
        ]);
        
        // Create permissions
        $permissions = [
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
        ];
        
        foreach ($permissions as $permissionName) {
            $permission = \App\Models\Permission::create([
                'name' => $permissionName,
                'descripcion' => $permissionName,
            ]);
            
            // Attach permission to role
            $this->role->permissions()->attach($permission->id);
        }
    }

    /** @test */
    public function api_store_accepts_valid_almacen_id()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);

        $admin = User::factory()->create(['role_id' => $this->role->id]);
        $this->actingAs($admin);

        $response = $this->postJson('/api/usuarios', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role_id' => $this->role->id,
            'almacen_id' => $almacen->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'almacen_id' => $almacen->id,
        ]);
    }

    /** @test */
    public function api_store_accepts_null_almacen_id()
    {
        $admin = User::factory()->create(['role_id' => $this->role->id]);
        $this->actingAs($admin);

        $response = $this->postJson('/api/usuarios', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role_id' => $this->role->id,
            'almacen_id' => null,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'almacen_id' => null,
        ]);
    }

    /** @test */
    public function api_store_rejects_invalid_almacen_id()
    {
        $admin = User::factory()->create(['role_id' => $this->role->id]);
        $this->actingAs($admin);

        $response = $this->postJson('/api/usuarios', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role_id' => $this->role->id,
            'almacen_id' => 99999, // Non-existent almacen
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('almacen_id');
    }

    /** @test */
    public function api_update_accepts_valid_almacen_id()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);

        $admin = User::factory()->create(['role_id' => $this->role->id]);
        $user = User::factory()->create(['almacen_id' => null]);
        $this->actingAs($admin);

        $response = $this->putJson("/api/usuarios/{$user->id}", [
            'almacen_id' => $almacen->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'almacen_id' => $almacen->id,
        ]);
    }

    /** @test */
    public function api_update_rejects_invalid_almacen_id()
    {
        $admin = User::factory()->create(['role_id' => $this->role->id]);
        $user = User::factory()->create(['almacen_id' => null]);
        $this->actingAs($admin);

        $response = $this->putJson("/api/usuarios/{$user->id}", [
            'almacen_id' => 99999, // Non-existent almacen
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('almacen_id');
    }

    /** @test */
    public function api_update_accepts_null_almacen_id()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);

        $admin = User::factory()->create(['role_id' => $this->role->id]);
        $user = User::factory()->create(['almacen_id' => $almacen->id]);
        $this->actingAs($admin);

        $response = $this->putJson("/api/usuarios/{$user->id}", [
            'almacen_id' => null,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'almacen_id' => null,
        ]);
    }
}

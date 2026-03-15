<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBackwardCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_without_almacen_can_be_created()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertNull($user->almacen_id);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
    }

    /** @test */
    public function user_existing_methods_work_without_almacen()
    {
        $user = User::factory()->create(['almacen_id' => null]);

        // Test that existing methods still work
        $this->assertIsBool($user->isActive());
        $this->assertNull($user->role);
    }

    /** @test */
    public function user_existing_relationships_work_without_almacen()
    {
        $user = User::factory()->create(['almacen_id' => null]);

        // Test that existing relationships still work
        $this->assertNotNull($user->compras());
        $this->assertNotNull($user->ventas());
        $this->assertNotNull($user->movimientosCaja());
        $this->assertNotNull($user->cortesCaja());
        $this->assertNotNull($user->role());
    }

    /** @test */
    public function mass_assignment_works_without_almacen_id()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertNull($user->almacen_id);
    }

    /** @test */
    public function querying_users_works_with_mixed_almacen_assignments()
    {
        // Create users with and without almacen
        User::factory()->count(3)->create(['almacen_id' => null]);
        
        $users = User::all();

        $this->assertCount(3, $users);
        
        foreach ($users as $user) {
            $this->assertNull($user->almacen_id);
        }
    }
}

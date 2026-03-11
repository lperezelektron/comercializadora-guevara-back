<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Almacen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAlmacenRelationshipTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_belongs_to_almacen_relationship_works()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);
        
        $user = User::factory()->create(['almacen_id' => $almacen->id]);

        // Test the relationship
        $this->assertInstanceOf(Almacen::class, $user->almacen);
        $this->assertEquals($almacen->id, $user->almacen->id);
        $this->assertEquals('Almacen Test', $user->almacen->descripcion);
    }

    /** @test */
    public function user_almacen_relationship_returns_null_when_no_almacen_assigned()
    {
        $user = User::factory()->create(['almacen_id' => null]);

        $this->assertNull($user->almacen);
    }

    /** @test */
    public function almacen_has_many_usuarios_relationship_works()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);

        $user1 = User::factory()->create(['almacen_id' => $almacen->id]);
        $user2 = User::factory()->create(['almacen_id' => $almacen->id]);
        $user3 = User::factory()->create(['almacen_id' => null]);

        // Test the relationship
        $usuarios = $almacen->usuarios;
        
        $this->assertCount(2, $usuarios);
        $this->assertTrue($usuarios->contains($user1));
        $this->assertTrue($usuarios->contains($user2));
        $this->assertFalse($usuarios->contains($user3));
    }

    /** @test */
    public function eager_loading_almacen_relationship_works()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);

        User::factory()->create(['almacen_id' => $almacen->id]);
        User::factory()->create(['almacen_id' => $almacen->id]);

        // Eager load the relationship
        $users = User::with('almacen')->get();

        $this->assertCount(2, $users);
        foreach ($users as $user) {
            $this->assertInstanceOf(Almacen::class, $user->almacen);
            $this->assertEquals($almacen->id, $user->almacen->id);
        }
    }

    /** @test */
    public function deleting_almacen_sets_user_almacen_id_to_null()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);

        $user1 = User::factory()->create(['almacen_id' => $almacen->id]);
        $user2 = User::factory()->create(['almacen_id' => $almacen->id]);

        // Verify users have almacen assigned
        $this->assertEquals($almacen->id, $user1->almacen_id);
        $this->assertEquals($almacen->id, $user2->almacen_id);

        // Delete the almacen
        $almacen->delete();

        // Refresh users from database
        $user1->refresh();
        $user2->refresh();

        // Verify almacen_id is now null (ON DELETE SET NULL)
        $this->assertNull($user1->almacen_id);
        $this->assertNull($user2->almacen_id);
    }

    /** @test */
    public function user_can_be_created_with_almacen_id_via_mass_assignment()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'almacen_id' => $almacen->id,
        ]);

        $this->assertEquals($almacen->id, $user->almacen_id);
        $this->assertInstanceOf(Almacen::class, $user->almacen);
    }

    /** @test */
    public function user_almacen_id_can_be_updated()
    {
        $almacen1 = Almacen::create([
            'descripcion' => 'Almacen 1',
            'direccion' => 'Address 1',
            'activo' => true,
        ]);

        $almacen2 = Almacen::create([
            'descripcion' => 'Almacen 2',
            'direccion' => 'Address 2',
            'activo' => true,
        ]);

        $user = User::factory()->create(['almacen_id' => $almacen1->id]);

        // Verify initial assignment
        $this->assertEquals($almacen1->id, $user->almacen_id);

        // Update to different almacen
        $user->update(['almacen_id' => $almacen2->id]);

        $this->assertEquals($almacen2->id, $user->almacen_id);
        $this->assertEquals($almacen2->id, $user->almacen->id);
    }

    /** @test */
    public function user_almacen_id_can_be_set_to_null()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);

        $user = User::factory()->create(['almacen_id' => $almacen->id]);

        // Verify initial assignment
        $this->assertEquals($almacen->id, $user->almacen_id);

        // Set to null
        $user->update(['almacen_id' => null]);

        $this->assertNull($user->almacen_id);
        $this->assertNull($user->almacen);
    }
}

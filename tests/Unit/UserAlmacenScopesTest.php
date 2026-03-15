<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Almacen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAlmacenScopesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function porAlmacen_scope_filters_users_by_almacen_id()
    {
        // Create two almacenes
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

        // Create users assigned to different almacenes
        $user1 = User::factory()->create(['almacen_id' => $almacen1->id]);
        $user2 = User::factory()->create(['almacen_id' => $almacen1->id]);
        $user3 = User::factory()->create(['almacen_id' => $almacen2->id]);
        $user4 = User::factory()->create(['almacen_id' => null]);

        // Query users for almacen1
        $usersInAlmacen1 = User::porAlmacen($almacen1->id)->get();

        // Assert only users assigned to almacen1 are returned
        $this->assertCount(2, $usersInAlmacen1);
        $this->assertTrue($usersInAlmacen1->contains($user1));
        $this->assertTrue($usersInAlmacen1->contains($user2));
        $this->assertFalse($usersInAlmacen1->contains($user3));
        $this->assertFalse($usersInAlmacen1->contains($user4));
    }

    /** @test */
    public function porAlmacen_scope_returns_empty_collection_when_no_users_assigned()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);

        // Create users without this almacen
        User::factory()->create(['almacen_id' => null]);

        $users = User::porAlmacen($almacen->id)->get();

        $this->assertCount(0, $users);
    }

    /** @test */
    public function sinAlmacen_scope_filters_users_without_almacen()
    {
        // Create an almacen
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);

        // Create users with and without almacen
        $userWithAlmacen1 = User::factory()->create(['almacen_id' => $almacen->id]);
        $userWithAlmacen2 = User::factory()->create(['almacen_id' => $almacen->id]);
        $userWithoutAlmacen1 = User::factory()->create(['almacen_id' => null]);
        $userWithoutAlmacen2 = User::factory()->create(['almacen_id' => null]);

        // Query users without almacen
        $usersWithoutAlmacen = User::sinAlmacen()->get();

        // Assert only users without almacen are returned
        $this->assertCount(2, $usersWithoutAlmacen);
        $this->assertTrue($usersWithoutAlmacen->contains($userWithoutAlmacen1));
        $this->assertTrue($usersWithoutAlmacen->contains($userWithoutAlmacen2));
        $this->assertFalse($usersWithoutAlmacen->contains($userWithAlmacen1));
        $this->assertFalse($usersWithoutAlmacen->contains($userWithAlmacen2));
    }

    /** @test */
    public function sinAlmacen_scope_returns_empty_collection_when_all_users_have_almacen()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);

        // Create users all with almacen
        User::factory()->create(['almacen_id' => $almacen->id]);
        User::factory()->create(['almacen_id' => $almacen->id]);

        $users = User::sinAlmacen()->get();

        $this->assertCount(0, $users);
    }
}

<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Almacen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAlmacenHelperMethodsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function hasAlmacen_returns_true_when_user_has_almacen_assigned()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);
        $user = User::factory()->create(['almacen_id' => $almacen->id]);

        $this->assertTrue($user->hasAlmacen());
    }

    /** @test */
    public function hasAlmacen_returns_false_when_user_has_no_almacen_assigned()
    {
        $user = User::factory()->create(['almacen_id' => null]);

        $this->assertFalse($user->hasAlmacen());
    }

    /** @test */
    public function getAlmacenId_returns_almacen_id_when_assigned()
    {
        $almacen = Almacen::create([
            'descripcion' => 'Almacen Test',
            'direccion' => 'Test Address',
            'activo' => true,
        ]);
        $user = User::factory()->create(['almacen_id' => $almacen->id]);

        $this->assertEquals($almacen->id, $user->getAlmacenId());
    }

    /** @test */
    public function getAlmacenId_returns_null_when_no_almacen_assigned()
    {
        $user = User::factory()->create(['almacen_id' => null]);

        $this->assertNull($user->getAlmacenId());
    }
}

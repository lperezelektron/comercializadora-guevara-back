<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'telefono',
        'direccion',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Relaciones
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function compras()
    {
        return $this->hasMany(Compra::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function movimientosCaja()
    {
        return $this->hasMany(Caja::class);
    }

    public function cortesCaja()
    {
        return $this->hasMany(CorteCaja::class);
    }

    // Métodos auxiliares
    public function hasRole($roleName)
    {
        return $this->role && $this->role->name === $roleName;
    }

    public function hasPermission($permissionName)
    {
        return $this->role && 
               $this->role->permissions()
                          ->where('name', $permissionName)
                          ->exists();
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isAdmin()
    {
        return $this->hasRole('admin');
    }
}
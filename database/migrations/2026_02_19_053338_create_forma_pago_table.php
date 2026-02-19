<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forma_pago', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 255); // Efectivo, Tarjeta, Transferencia, etc
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forma_pago');
    }
};
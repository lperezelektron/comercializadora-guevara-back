<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->string('direccion', 255)->nullable();
            $table->string('ciudad', 255)->nullable();
            $table->string('rfc', 255)->nullable();
            $table->string('telefono', 255)->nullable();
            $table->tinyInteger('dias_credito')->default(0); // Días de crédito
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
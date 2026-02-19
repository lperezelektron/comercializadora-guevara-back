<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kardex', function (Blueprint $table) {
            $table->id();
            $table->string('lote_id', 50);
            $table->date('fecha');
            $table->string('movimiento', 255); // Descripción del movimiento
            $table->enum('tipo', ['entrada', 'salida']);
            $table->string('documento', 255); // Número de documento
            $table->integer('cte_prv'); // ID de cliente o proveedor
            $table->decimal('cantidad', 10, 2);
            $table->decimal('empaque', 10, 2)->nullable();
            $table->decimal('costo', 10, 2);
            $table->decimal('precio', 10, 2)->nullable();
            $table->timestamps();
            
            // Foreign key al lote
            $table->foreign('lote_id')->references('lote')->on('compras_detalle')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');
            $table->foreignId('articulo_id')->constrained('articulos')->onDelete('restrict');
            $table->string('lote_id', 50); // Referencia al lote de compras_detalle
            $table->decimal('cantidad', 10, 2);
            $table->decimal('empaque', 10, 2);
            $table->decimal('precio', 10, 2);
            $table->decimal('impuestos', 10, 2)->default(0);
            $table->timestamps();
            
            // Foreign key a compras_detalle
            $table->foreign('lote_id')->references('lote')->on('compras_detalle')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_detalle');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_detalle', function (Blueprint $table) {
            $table->id();
            $table->string('lote', 50)->unique(); // Número de lote generado
            $table->foreignId('compra_id')->constrained('compras')->onDelete('cascade');
            $table->foreignId('articulo_id')->constrained('articulos')->onDelete('restrict');
            $table->string('variedad', 255);
            $table->decimal('cantidad', 10, 2);
            $table->decimal('empaque', 10, 2);
            $table->decimal('costo', 10, 2);
            $table->decimal('impuestos', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_detalle');
    }
};
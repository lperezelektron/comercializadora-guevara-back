<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained('almacenes')->onDelete('restrict');
            $table->foreignId('articulo_id')->constrained('articulos')->onDelete('restrict');
            $table->string('variedad', 50); // Chica, Mediana, Grande, etc
            $table->decimal('existencia', 10, 2)->default(0);
            $table->decimal('precio', 10, 2); // Precio de venta
            $table->decimal('precio_min', 10, 2); // Precio mínimo
            $table->decimal('costo', 10, 2); // Costo actual
            $table->decimal('empaque', 10, 2)->default(0); // Unidades por empaque
            $table->timestamps();
            
            // Índice único para evitar duplicados
            $table->unique(['almacen_id', 'articulo_id', 'variedad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctas_x_pagar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->onDelete('restrict');
            $table->foreignId('proveedor_id')->constrained('proveedores')->onDelete('restrict');
            $table->date('fecha');
            $table->date('vencimiento');
            $table->decimal('importe', 10, 2);
            $table->decimal('saldo', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ctas_x_pagar');
    }
};
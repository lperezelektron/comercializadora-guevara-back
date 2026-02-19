<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctas_x_cobrar', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->date('vencimiento');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('restrict');
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('restrict');
            $table->decimal('importe', 10, 2);
            $table->decimal('saldo', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ctas_x_cobrar');
    }
};
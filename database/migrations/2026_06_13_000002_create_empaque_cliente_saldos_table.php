<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empaque_cliente_saldos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empaque_id')->constrained('empaques')->onDelete('restrict');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('restrict');
            $table->decimal('saldo', 10, 2)->default(0)->comment('Positivo = cliente debe empaques');
            $table->timestamps();

            $table->unique(['empaque_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empaque_cliente_saldos');
    }
};

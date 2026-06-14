<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empaque_movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->date('fecha');
            $table->foreignId('empaque_id')->constrained('empaques')->onDelete('restrict');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('restrict');
            $table->enum('tipo', ['salida', 'entrada'])
                  ->comment('salida = se prestan al cliente, entrada = devolucion del cliente');
            $table->decimal('cantidad', 10, 2);
            $table->text('notas')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index(['empaque_id', 'cliente_id']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empaque_movimientos');
    }
};

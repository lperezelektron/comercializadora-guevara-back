<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cxp_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cxp_id')->constrained('ctas_x_pagar')->onDelete('cascade');
            $table->date('fecha');
            $table->decimal('importe', 10, 2);
            $table->foreignId('f_pago_id')->constrained('forma_pago')->onDelete('restrict');
            $table->enum('tipo', ['abono', 'cargo'])->default('abono');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cxp_detalle');
    }
};
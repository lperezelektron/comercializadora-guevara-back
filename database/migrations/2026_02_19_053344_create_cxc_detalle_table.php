<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cxc_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cxc_id')->constrained('ctas_x_cobrar')->onDelete('cascade');
            $table->date('fecha');
            $table->decimal('importe', 10, 2);
            $table->foreignId('f_pago_id')->constrained('forma_pago')->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cxc_detalle');
    }
};
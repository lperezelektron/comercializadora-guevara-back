<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->enum('tipo', ['entrada', 'salida']);
            $table->decimal('cantidad', 10, 2);
            $table->string('referencia', 255);
            $table->foreignId('corte_id')->nullable()->constrained('corte_caja')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja');
    }
};
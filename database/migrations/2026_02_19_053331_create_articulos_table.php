<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articulos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50);
            $table->string('nombre_corto', 255);
            $table->string('unidad', 5); // kg, pza, caja, etc
            $table->foreignId('categoria_id')->constrained('categorias')->onDelete('restrict');
            $table->boolean('activo')->default(true);
            $table->string('imagen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articulos');
    }
};
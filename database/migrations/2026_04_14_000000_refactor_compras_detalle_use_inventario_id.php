<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras_detalle', function (Blueprint $table) {
            // Quitar columnas del modelo antiguo de lotes
            $table->dropUnique(['lote']);
            $table->dropForeign(['articulo_id']);
            $table->dropColumn(['lote', 'articulo_id', 'variedad']);

            // Agregar FK al inventario (el lote ahora es el registro de inventario)
            $table->foreignId('inventario_id')
                  ->after('compra_id')
                  ->constrained('inventario')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('compras_detalle', function (Blueprint $table) {
            $table->dropForeign(['inventario_id']);
            $table->dropColumn('inventario_id');

            $table->string('lote', 50)->unique()->after('id');
            $table->foreignId('articulo_id')
                  ->after('compra_id')
                  ->constrained('articulos')
                  ->onDelete('restrict');
            $table->string('variedad', 255)->after('articulo_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kardex', function (Blueprint $table) {
            // 1. Eliminar FK incorrecta hacia compras_detalle.lote
            $table->dropForeign(['lote_id']);

            // 2. Cambiar el tipo de columna de string a unsignedBigInteger
            //    para que coincida con inventario.id
            $table->unsignedBigInteger('lote_id')->change();

            // 3. Crear FK correcta hacia inventario.id
            $table->foreign('lote_id')
                  ->references('id')
                  ->on('inventario')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('kardex', function (Blueprint $table) {
            // Revertir: quitar FK nueva
            $table->dropForeign(['lote_id']);

            // Volver a string
            $table->string('lote_id', 50)->change();

            // Restaurar FK original hacia compras_detalle.lote
            $table->foreign('lote_id')
                  ->references('lote')
                  ->on('compras_detalle')
                  ->onDelete('restrict');
        });
    }
};

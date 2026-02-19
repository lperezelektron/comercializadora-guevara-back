<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar user_id a compras
        Schema::table('compras', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('proveedor_id')
                  ->constrained('users')->onDelete('set null');
        });

        // Agregar user_id a ventas
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('almacen_id')
                  ->constrained('users')->onDelete('set null');
        });

        // Agregar user_id a caja
        Schema::table('caja', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('fecha')
                  ->constrained('users')->onDelete('set null');
        });

        // Agregar user_id a corte_caja
        Schema::table('corte_caja', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('fecha')
                  ->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('caja', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('corte_caja', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('caja', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->after('user_id')->constrained('almacenes');
        });

        Schema::table('corte_caja', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->after('user_id')->constrained('almacenes');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->after('proveedor_id')->constrained('almacenes');
        });
    }

    public function down(): void
    {
        Schema::table('caja', function (Blueprint $table) {
            $table->dropForeign(['almacen_id']);
            $table->dropColumn('almacen_id');
        });

        Schema::table('corte_caja', function (Blueprint $table) {
            $table->dropForeign(['almacen_id']);
            $table->dropColumn('almacen_id');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropForeign(['almacen_id']);
            $table->dropColumn('almacen_id');
        });
    }
};

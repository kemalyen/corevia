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
        Schema::table('ingestions', function (Blueprint $table) {
            $table->dropUnique(['source', 'dataset', 'checksum']);
            $table->dropColumn('source');
            $table->unique(['dataset', 'checksum']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id']);
            $table->dropColumn('source');
            $table->unique('external_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id']);
            $table->dropColumn('source');
            $table->unique('external_id');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id']);
            $table->dropColumn('source');
            $table->unique('external_id');
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropUnique(['sales_order_id', 'source', 'external_id']);
            $table->dropColumn('source');
            $table->unique(['sales_order_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropUnique(['sales_order_id', 'external_id']);
            $table->string('source', 64)->default('legacy');
            $table->unique(['sales_order_id', 'source', 'external_id']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->string('source', 64)->default('legacy');
            $table->unique(['source', 'external_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->string('source', 64)->default('legacy');
            $table->unique(['source', 'external_id']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->string('source', 64)->default('legacy');
            $table->unique(['source', 'external_id']);
        });

        Schema::table('ingestions', function (Blueprint $table) {
            $table->dropUnique(['dataset', 'checksum']);
            $table->string('source', 64)->default('legacy');
            $table->unique(['source', 'dataset', 'checksum']);
        });
    }
};

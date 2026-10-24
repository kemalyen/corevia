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
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('source', 64)->nullable();
            $table->string('external_id')->nullable();
            $table->unique(['source', 'external_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('source', 64)->nullable();
            $table->string('external_id')->nullable();
            $table->char('currency', 3)->nullable();
            $table->unique(['source', 'external_id']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('source', 64)->nullable();
            $table->string('external_id')->nullable();
            $table->char('currency', 3)->nullable();
            $table->unique(['source', 'external_id']);
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->string('source', 64)->nullable();
            $table->string('external_id')->nullable();
            $table->unique(['sales_order_id', 'source', 'external_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 18, 2)->change();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 2)->change();
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('quantity', 18, 4)->change();
            $table->decimal('unit_price', 18, 2)->change();
            $table->decimal('total_price', 18, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropUnique(['sales_order_id', 'source', 'external_id']);
            $table->dropColumn(['source', 'external_id']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id']);
            $table->dropColumn(['source', 'external_id', 'currency']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id']);
            $table->dropColumn(['source', 'external_id', 'currency']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id']);
            $table->dropColumn(['source', 'external_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->change();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 10, 2)->change();
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->decimal('unit_price', 10, 2)->change();
            $table->decimal('total_price', 10, 2)->change();
        });
    }
};

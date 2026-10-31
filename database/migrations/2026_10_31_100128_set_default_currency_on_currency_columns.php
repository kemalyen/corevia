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
        Schema::table('products', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->default('GBP')->change();
        });
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->default('GBP')->change();
        });
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->default('GBP')->change();
        });
        Schema::table('account_product_prices', function (Blueprint $table) {
            $table->char('currency', 3)->default('GBP')->change();
        });
        Schema::table('staged_sales_order_rows', function (Blueprint $table) {
            $table->char('currency', 3)->default('GBP')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->default(null)->change();
        });
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->default(null)->change();
        });
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->default(null)->change();
        });
        Schema::table('account_product_prices', function (Blueprint $table) {
            $table->char('currency', 3)->default(null)->change();
        });
        Schema::table('staged_sales_order_rows', function (Blueprint $table) {
            $table->char('currency', 3)->default(null)->change();
        });
    }
};

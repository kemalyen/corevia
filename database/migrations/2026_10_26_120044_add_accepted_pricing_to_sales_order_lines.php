<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('unit_price', 18, 4)->change();
            $table->char('currency', 3)->nullable()->after('unit_price');
            $table->string('price_source', 32)->nullable()->after('currency');
        });

        DB::statement('UPDATE sales_order_lines SET currency = (SELECT currency FROM sales_orders WHERE sales_orders.id = sales_order_lines.sales_order_id)');
        DB::table('sales_order_lines')->whereNotNull('external_id')->update(['price_source' => 'erp_confirmed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->change();
            $table->dropColumn(['currency', 'price_source']);
        });
    }
};

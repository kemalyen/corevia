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
        Schema::table('staged_sales_order_rows', function (Blueprint $table) {
            $table->renameColumn('customer_external_id', 'account_external_id');
        });

        DB::table('ingestions')
            ->where('dataset', 'customers')
            ->update(['dataset' => 'accounts']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ingestions')
            ->where('dataset', 'accounts')
            ->update(['dataset' => 'customers']);

        Schema::table('staged_sales_order_rows', function (Blueprint $table) {
            $table->renameColumn('account_external_id', 'customer_external_id');
        });
    }
};

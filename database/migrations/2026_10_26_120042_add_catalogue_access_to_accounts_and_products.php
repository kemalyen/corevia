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
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('product_access_mode', 32)->default('restricted')->after('account_status');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 18, 4)->nullable()->change();
            $table->string('unit_of_measure', 32)->nullable()->after('currency');
        });

        DB::table('accounts')->update(['product_access_mode' => 'all']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable(false)->change();
            $table->dropColumn('unit_of_measure');
        });
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('product_access_mode');
        });
    }
};

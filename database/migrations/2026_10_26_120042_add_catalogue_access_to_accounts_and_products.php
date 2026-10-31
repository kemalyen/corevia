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

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_product_access_mode_check CHECK (product_access_mode IN ('all', 'restricted'))");
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_nonnegative_check CHECK (price IS NULL OR price >= 0)');
        }

        DB::table('accounts')->update(['product_access_mode' => 'all']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_price_nonnegative_check');
            DB::statement('ALTER TABLE accounts DROP CONSTRAINT IF EXISTS accounts_product_access_mode_check');
        }

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable(false)->change();
            $table->dropColumn('unit_of_measure');
        });
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('product_access_mode');
        });
    }
};

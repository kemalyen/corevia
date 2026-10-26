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
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX products_lower_sku_pattern_index ON products (LOWER(sku) text_pattern_ops)');
            DB::statement('CREATE INDEX products_lower_name_pattern_index ON products (LOWER(name) text_pattern_ops)');

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->index('name', 'products_name_search_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS products_lower_sku_pattern_index');
            DB::statement('DROP INDEX IF EXISTS products_lower_name_pattern_index');

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_name_search_index');
        });
    }
};

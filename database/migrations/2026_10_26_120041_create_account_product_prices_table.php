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
        Schema::create('account_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3);
            $table->string('unit_of_measure', 32)->default('');
            $table->timestamps();

            $table->unique(
                ['account_id', 'product_id', 'currency', 'unit_of_measure'],
                'account_product_prices_dimensions_unique',
            );
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE account_product_prices ADD CONSTRAINT account_product_prices_amount_nonnegative CHECK (amount >= 0)',
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_product_prices');
    }
};

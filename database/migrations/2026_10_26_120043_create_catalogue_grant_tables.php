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
        Schema::create('account_product_grants', function (Blueprint $table) {
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['account_id', 'product_id']);
        });
        Schema::create('account_product_category_grants', function (Blueprint $table) {
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['account_id', 'product_category_id'], 'account_product_category_grants_primary');
        });
        Schema::create('product_category_product', function (Blueprint $table) {
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['product_category_id', 'product_id']);
            $table->index(['product_id', 'product_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_category_product');
        Schema::dropIfExists('account_product_category_grants');
        Schema::dropIfExists('account_product_grants');
    }
};

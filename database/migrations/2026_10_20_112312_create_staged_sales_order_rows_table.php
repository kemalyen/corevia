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
        Schema::create('staged_sales_order_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('row_number');
            $table->string('order_external_id');
            $table->string('order_number');
            $table->date('order_date');
            $table->string('customer_external_id');
            $table->string('status', 32);
            $table->char('currency', 3);
            $table->decimal('total_amount', 18, 2);
            $table->string('line_external_id');
            $table->string('line_number')->nullable();
            $table->string('product_external_id');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->text('description')->nullable();
            $table->string('sku')->nullable();
            $table->string('unit_of_measure', 32)->nullable();
            $table->timestamps();

            $table->unique(['ingestion_id', 'row_number']);
            $table->index(['ingestion_id', 'order_external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staged_sales_order_rows');
    }
};

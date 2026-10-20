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
        Schema::create('ingestion_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('row_number')->nullable();
            $table->string('field')->nullable();
            $table->string('code', 64);
            $table->text('message');
            $table->timestamps();

            $table->index(['ingestion_id', 'row_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingestion_errors');
    }
};

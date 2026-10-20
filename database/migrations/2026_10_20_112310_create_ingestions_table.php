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
        Schema::create('ingestions', function (Blueprint $table) {
            $table->id();
            $table->string('source', 64);
            $table->string('dataset', 32);
            $table->string('original_filename');
            $table->string('processing_filename')->nullable()->unique();
            $table->char('checksum', 64);
            $table->string('status', 32);
            $table->unsignedBigInteger('file_size');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('valid_rows')->default(0);
            $table->unsignedBigInteger('failed_rows')->default(0);
            $table->unsignedBigInteger('created_records')->default(0);
            $table->unsignedBigInteger('updated_records')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('discovered_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'dataset', 'checksum']);
            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingestions');
    }
};

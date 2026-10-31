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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('origin', 32)->default('erp_import')->after('external_id');
            $table->string('erp_sync_state', 32)->default('accepted')->after('origin');
            $table->uuid('portal_submission_token')->nullable()->unique()->after('erp_sync_state');
            $table->foreignId('submitted_by_user_id')->nullable()->after('portal_submission_token')->constrained('users')->nullOnDelete();
            $table->string('customer_reference')->nullable()->after('submitted_by_user_id');

            $table->index(['account_id', 'order_date']);
            $table->index(['account_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'order_date']);
            $table->dropIndex(['account_id', 'status']);
            $table->dropForeign(['submitted_by_user_id']);
            $table->dropUnique(['portal_submission_token']);
            $table->dropColumn([
                'origin',
                'erp_sync_state',
                'portal_submission_token',
                'submitted_by_user_id',
                'customer_reference',
            ]);
        });
    }
};

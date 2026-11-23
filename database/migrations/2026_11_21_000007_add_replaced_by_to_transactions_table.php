<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep an explicit audit trail when a customer changes payment method.
     * A replaced order can never activate the invitation, even if a late
     * successful callback arrives from the payment provider.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('replaced_by_order_id')->nullable()->after('idempotency_key');
            $table->index('replaced_by_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['replaced_by_order_id']);
            $table->dropColumn('replaced_by_order_id');
        });
    }
};

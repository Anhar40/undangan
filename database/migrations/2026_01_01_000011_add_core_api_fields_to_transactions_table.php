<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_label')->nullable()->after('payment_type');
            $table->string('payment_reference')->nullable()->after('payment_label');
            $table->timestamp('payment_expires_at')->nullable()->after('transaction_time');
            $table->string('idempotency_key', 191)->nullable()->after('order_id');
            $table->json('payment_instructions')->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'payment_label',
                'payment_reference',
                'payment_expires_at',
                'idempotency_key',
                'payment_instructions',
            ]);
        });
    }
};

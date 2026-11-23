<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('invitation_id')->constrained()->restrictOnDelete();
            $table->string('order_id', 191)->unique();
            $table->decimal('gross_amount', 15, 2);
            $table->string('payment_method')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('transaction_status', 20)->default('PENDING')->index();
            $table->string('fraud_status', 20)->nullable();
            $table->string('midtrans_transaction_id', 191)->nullable()->index();
            $table->string('snap_token')->nullable();
            $table->string('snap_url')->nullable();
            $table->timestamp('transaction_time')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('notification_count')->default(0);
            $table->json('midtrans_response')->nullable();
            $table->timestamps();

            $table->index(['invitation_id', 'transaction_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('voucher_id')->nullable()->after('user_id')->constrained('vouchers')->nullOnDelete();
            $table->string('voucher_code', 50)->nullable()->after('voucher_id');
            $table->decimal('original_amount', 15, 2)->nullable()->after('gross_amount');
            $table->decimal('voucher_discount_amount', 15, 2)->default(0)->after('original_amount');
            $table->timestamp('voucher_redeemed_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('voucher_id');
            $table->dropColumn(['voucher_code', 'original_amount', 'voucher_discount_amount', 'voucher_redeemed_at']);
        });
    }
};

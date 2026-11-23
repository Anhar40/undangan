<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->timestamp('active_expires_at')->nullable()->after('activated_at');
        });

        $driver = DB::getDriverName();
        $intervalExpr = $driver === 'pgsql'
            ? "activated_at + interval '1 year'"
            : "DATE_ADD(activated_at, INTERVAL 1 YEAR)";

        DB::table('invitations')
            ->where('status', 'ACTIVE')
            ->whereNotNull('activated_at')
            ->whereNull('active_expires_at')
            ->update([
                'active_expires_at' => DB::raw($intervalExpr),
            ]);
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropColumn('active_expires_at');
        });
    }
};

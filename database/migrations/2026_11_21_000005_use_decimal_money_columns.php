<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fresh installs already get DECIMAL from the create migrations. Existing
     * MySQL installations are upgraded here so every money column follows the
     * same DECIMAL rule (never floating point).
     */
    public function up(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('templates', function (Blueprint $table): void {
            $table->decimal('price', 15, 2)->default(0)->change();
        });

        Schema::table('invitations', function (Blueprint $table): void {
            $table->decimal('price', 15, 2)->default(0)->change();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->decimal('gross_amount', 15, 2)->change();
        });
    }

    public function down(): void
    {
        // Intentionally not reversible. Once real DECIMAL values (for example
        // 150000.50) exist, converting back to UNSIGNED BIGINT would truncate
        // or reject them under MySQL strict mode. The DECIMAL definition is the
        // correct permanent schema, so a rollback must not destroy money data.
    }
};

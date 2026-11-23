<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fresh installations already get this constraint from the create
     * migration. Existing MySQL/MariaDB installations are upgraded here so
     * payment history can never be hard-deleted by removing an invitation.
     */
    public function up(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['invitation_id']);
            $table->foreign('invitation_id')
                ->references('id')->on('invitations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['invitation_id']);
            $table->foreign('invitation_id')
                ->references('id')->on('invitations')
                ->cascadeOnDelete();
        });
    }
};

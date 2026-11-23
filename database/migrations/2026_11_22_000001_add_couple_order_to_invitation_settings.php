<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A null value means "legacy invitation": keep the presentation order the
     * templates already used before this setting existed.
     */
    public function up(): void
    {
        Schema::table('invitation_settings', function (Blueprint $table): void {
            $table->string('couple_order', 20)->nullable()->after('font_family');
        });
    }

    public function down(): void
    {
        Schema::table('invitation_settings', function (Blueprint $table): void {
            $table->dropColumn('couple_order');
        });
    }
};

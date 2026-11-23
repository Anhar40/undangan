<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('guest_name');
            $table->string('attendance_status', 20)->default('HADIR');
            $table->unsignedInteger('guest_count')->default(1);
            $table->text('message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['invitation_id', 'created_at']);
        });

        Schema::create('invitation_greetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('guest_name');
            $table->text('message');
            $table->string('attendance_status', 20)->nullable();
            $table->string('status', 20)->default('approved')->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['invitation_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_greetings');
        Schema::dropIfExists('invitation_guests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 20)->default('AKAD');
            $table->string('title')->nullable();
            $table->date('event_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->string('location_name')->nullable();
            $table->text('address')->nullable();
            $table->string('maps_url')->nullable();
            $table->text('description')->nullable();
            $table->string('live_stream_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invitation_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_events');
    }
};

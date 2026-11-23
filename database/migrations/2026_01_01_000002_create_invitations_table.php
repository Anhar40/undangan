<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->restrictOnDelete();

            // Snapshot of the template at creation time. Admin edits to a
            // template must never change invitations already created (PRD 4.4).
            $table->string('template_name');
            $table->string('template_version', 20);
            $table->string('template_path');
            $table->json('template_snapshot')->nullable();

            $table->string('title');
            $table->string('slug', 191)->unique();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->string('payment_status', 20)->default('PENDING')->index();
            $table->decimal('price', 15, 2)->default(0);

            $table->timestamp('preview_started_at')->nullable();
            $table->timestamp('preview_expires_at')->nullable()->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['template_id', 'template_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};

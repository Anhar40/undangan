<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('template_path');
            $table->decimal('price', 15, 2)->default(0);
            $table->string('version', 20)->default('1.0');
            $table->string('status', 20)->default('active')->index();
            $table->json('features')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('musics', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('file_path', 191);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('musics');
        Schema::dropIfExists('templates');
    }
};

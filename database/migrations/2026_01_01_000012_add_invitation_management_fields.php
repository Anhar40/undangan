<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->string('cover_photo')->nullable()->after('template_snapshot');
            $table->timestamp('template_locked_at')->nullable()->after('template_version');
        });

        Schema::table('invitation_settings', function (Blueprint $table): void {
            $table->string('whatsapp_number', 30)->nullable()->after('music_autoplay');
            $table->string('whatsapp_display_name', 120)->nullable()->after('whatsapp_number');
            $table->text('whatsapp_message')->nullable()->after('whatsapp_display_name');
        });

        Schema::create('invitation_slug_redirects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('old_slug', 191)->unique();
            $table->timestamps();

            $table->index(['invitation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_slug_redirects');

        Schema::table('invitation_settings', function (Blueprint $table): void {
            $table->dropColumn(['whatsapp_number', 'whatsapp_display_name', 'whatsapp_message']);
        });

        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropColumn(['cover_photo', 'template_locked_at']);
        });
    }
};

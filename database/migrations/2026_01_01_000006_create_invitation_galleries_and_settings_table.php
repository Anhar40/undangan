<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_galleries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('image');
            $table->string('caption')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invitation_id', 'sort_order']);
        });

        Schema::create('invitation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('theme', 50)->nullable();
            $table->string('primary_color', 20)->nullable();
            $table->string('secondary_color', 20)->nullable();
            $table->string('font_family', 100)->nullable();
            $table->foreignId('music_id')->nullable()->constrained('musics')->nullOnDelete();
            $table->string('music_url')->nullable();
            $table->boolean('music_autoplay')->default(false);
            $table->boolean('show_stories')->default(true);
            $table->boolean('show_gallery')->default(true);
            $table->boolean('show_families')->default(true);
            $table->boolean('show_gift')->default(true);
            $table->boolean('show_rsvp')->default(true);
            $table->json('custom_settings')->nullable();
            $table->timestamps();

            $table->unique('invitation_id');
        });

        // Bank/e-wallet accounts and gift address shown in the templates.
        Schema::create('invitation_gift_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('qr_image')->nullable();
            $table->text('gift_address')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invitation_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_gift_accounts');
        Schema::dropIfExists('invitation_settings');
        Schema::dropIfExists('invitation_galleries');
    }
};

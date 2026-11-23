<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['invitation_grooms', 'invitation_brides'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
                $table->string('full_name')->nullable();
                $table->string('nickname')->nullable();
                $table->string('photo')->nullable();
                $table->string('father_name')->nullable();
                $table->string('mother_name')->nullable();
                $table->string('instagram')->nullable();
                $table->json('social_links')->nullable();
                $table->timestamps();

                $table->unique('invitation_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_brides');
        Schema::dropIfExists('invitation_grooms');
    }
};

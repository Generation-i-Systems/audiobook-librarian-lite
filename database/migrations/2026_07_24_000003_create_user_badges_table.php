<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('device_id')->nullable();
            $table->foreignId('badge_id')->constrained()->onDelete('cascade');
            $table->timestamp('earned_at');
            $table->json('criteria_met')->nullable();
            $table->integer('progress_value')->nullable();
            $table->boolean('is_notified')->default(false);
            $table->integer('tier_level')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'badge_id']);
            $table->index(['device_id', 'badge_id']);
            $table->index(['earned_at']);
            $table->index(['is_notified']);
            $table->unique(['user_id', 'badge_id', 'tier_level'], 'unique_user_badge_tier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('friend_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('recipient_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->boolean('is_shown')->default(false);
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'status']);
            $table->index(['sender_id', 'status']);
            $table->index(['recipient_id', 'is_shown']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friend_invitations');
    }
};

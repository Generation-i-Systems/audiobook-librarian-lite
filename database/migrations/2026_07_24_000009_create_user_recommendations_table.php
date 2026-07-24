<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('user_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('recipient_id')->constrained('users')->onDelete('cascade');
            // No books table in lite — recommendations are identified by title/author.
            $table->unsignedBigInteger('book_id')->nullable();
            $table->string('title')->nullable();
            $table->string('author')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_recommendations');
    }
};

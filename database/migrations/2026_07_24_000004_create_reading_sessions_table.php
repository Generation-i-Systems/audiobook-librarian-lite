<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('reading_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // No books table in lite — sessions are identified by title/author, not a
            // library FK. book_id is kept nullable/unconstrained for forward
            // compatibility only; nothing currently populates it.
            $table->unsignedBigInteger('book_id')->nullable();
            $table->string('title')->nullable();
            $table->string('author')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('pages')->nullable();
            $table->unsignedInteger('position_start')->nullable();
            $table->unsignedInteger('position_end')->nullable();
            $table->string('device', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index('ended_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_sessions');
    }
};

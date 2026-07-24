<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('user_book_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('playlist_id')->nullable()->constrained('playlists')->nullOnDelete();
            // No books table in lite — entries are identified by title/author, not a
            // library FK. book_id is kept nullable/unconstrained for forward
            // compatibility only; nothing currently populates it.
            $table->unsignedBigInteger('book_id')->nullable();
            $table->string('title')->nullable();
            $table->string('author')->nullable();
            $table->unsignedInteger('order')->nullable();
            $table->string('status', 20)->default('queue');
            $table->json('status_detail')->nullable();
            $table->unsignedSmallInteger('read_count')->default(0);
            $table->date('target_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'playlist_id']);
            $table->index(['user_id', 'finished_at']);
            $table->index(['user_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_book_status');
    }
};

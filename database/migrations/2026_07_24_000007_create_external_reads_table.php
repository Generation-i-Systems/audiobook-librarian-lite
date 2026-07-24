<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('external_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // No books table in lite — entries are identified by title/author.
            $table->unsignedBigInteger('book_id')->nullable();
            $table->string('title')->nullable();
            $table->string('author')->nullable();
            $table->string('origin')->default('external');
            $table->string('source')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_reads');
    }
};

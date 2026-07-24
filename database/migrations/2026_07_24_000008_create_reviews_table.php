<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            // No books table in lite — reviews are identified by title/author.
            $table->unsignedBigInteger('book_id')->nullable();
            $table->string('title')->nullable();
            $table->string('author')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('comment');
            $table->integer('age_rating');
            $table->string('content_rating');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

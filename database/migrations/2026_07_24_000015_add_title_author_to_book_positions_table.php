<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * No books table in lite — positions are identified by title/author, not
     * an integer book_id. Recreated (rather than altered in place) because
     * this table's primary key is composite and includes the column being
     * replaced. book_positions is an event-materialized cache table (derived
     * from listening_events), so it is safe to rebuild.
     */
    public function up(): void
    {
        Schema::dropIfExists('book_positions');

        Schema::create('book_positions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->string('author');
            $table->string('device_id', 255);
            $table->unsignedBigInteger('position_ms')->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0.00);
            $table->integer('current_chapter')->nullable();
            $table->string('current_chapter_name', 255)->nullable();
            $table->boolean('completed')->default(false);
            $table->unsignedBigInteger('last_event_timestamp_ms');
            $table->string('last_event_id', 255);
            $table->timestamps();

            $table->primary(['user_id', 'title', 'author', 'device_id']);
            $table->index(['user_id', 'title', 'author'], 'idx_bp_user_book');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_positions');

        Schema::create('book_positions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('book_id');
            $table->string('device_id', 255);
            $table->unsignedBigInteger('position_ms')->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0.00);
            $table->integer('current_chapter')->nullable();
            $table->string('current_chapter_name', 255)->nullable();
            $table->boolean('completed')->default(false);
            $table->unsignedBigInteger('last_event_timestamp_ms');
            $table->string('last_event_id', 255);
            $table->timestamps();

            $table->primary(['user_id', 'book_id', 'device_id']);
            $table->index(['user_id', 'book_id'], 'idx_bp_user_book');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};

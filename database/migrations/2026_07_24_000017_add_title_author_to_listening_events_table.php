<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * No books table in lite — events are identified by title/author, not an
     * integer book_id.
     */
    public function up(): void
    {
        Schema::table('listening_events', function (Blueprint $table) {
            $table->dropIndex('idx_user_book');
            $table->dropIndex('idx_book_timestamp');
        });

        Schema::table('listening_events', function (Blueprint $table) {
            $table->string('title')->default('')->after('book_id');
            $table->string('author')->default('')->after('title');
            $table->dropColumn('book_id');
        });

        Schema::table('listening_events', function (Blueprint $table) {
            $table->index(['user_id', 'title', 'author'], 'idx_user_book');
            $table->index(['title', 'author', 'timestamp_ms'], 'idx_book_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listening_events', function (Blueprint $table) {
            $table->dropIndex('idx_user_book');
            $table->dropIndex('idx_book_timestamp');
        });

        Schema::table('listening_events', function (Blueprint $table) {
            $table->unsignedBigInteger('book_id')->nullable()->after('user_id');
            $table->dropColumn(['title', 'author']);
        });

        Schema::table('listening_events', function (Blueprint $table) {
            $table->index(['user_id', 'book_id'], 'idx_user_book');
            $table->index(['book_id', 'timestamp_ms'], 'idx_book_timestamp');
        });
    }
};

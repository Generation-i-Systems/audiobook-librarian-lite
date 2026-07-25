<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * No books table in lite — listening statistics are identified by
     * title/author (already added), not an integer book_id.
     */
    public function up(): void
    {
        Schema::table('listening_statistics', function (Blueprint $table) {
            $table->dropIndex(['book_id', 'listening_date']);
        });

        Schema::table('listening_statistics', function (Blueprint $table) {
            $table->string('title')->default('')->nullable(false)->change();
            $table->string('author')->default('')->nullable(false)->change();
            $table->dropColumn('book_id');
        });

        Schema::table('listening_statistics', function (Blueprint $table) {
            $table->index(['title', 'author', 'listening_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listening_statistics', function (Blueprint $table) {
            $table->dropIndex(['title', 'author', 'listening_date']);
        });

        Schema::table('listening_statistics', function (Blueprint $table) {
            $table->unsignedBigInteger('book_id')->nullable()->after('id');
            $table->string('title')->nullable()->change();
            $table->string('author')->nullable()->change();
        });

        Schema::table('listening_statistics', function (Blueprint $table) {
            $table->index(['book_id', 'listening_date']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * No books table in lite — bookmarks are identified by title/author,
     * not an integer book_id. The books catalog this id pointed to no longer
     * exists, so the column is dropped entirely rather than kept as a
     * dead/opaque field.
     */
    public function up(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->string('author')->nullable()->after('title');
            $table->dropColumn('book_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->unsignedBigInteger('book_id')->nullable()->after('device_name');
            $table->dropColumn('author');
        });
    }
};

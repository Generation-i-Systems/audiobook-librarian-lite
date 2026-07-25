<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * No books table in lite — progress records are identified by
     * title/author, not an integer book_id.
     */
    public function up(): void
    {
        Schema::table('book_progress', function (Blueprint $table) {
            $table->dropUnique(['book_id', 'device_id']);
            $table->string('title')->default('')->after('book_id');
            $table->string('author')->default('')->after('title');
            $table->dropColumn('book_id');

            $table->unique(['title', 'author', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('book_progress', function (Blueprint $table) {
            $table->dropUnique(['title', 'author', 'device_id']);
            $table->unsignedBigInteger('book_id')->nullable()->after('id');
            $table->dropColumn(['title', 'author']);

            $table->unique(['book_id', 'device_id']);
        });
    }
};

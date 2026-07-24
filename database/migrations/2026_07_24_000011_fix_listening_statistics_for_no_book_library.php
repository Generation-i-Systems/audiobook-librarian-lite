<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('listening_statistics', function (Blueprint $table) {
            $table->dropForeign(['book_id']);
        });

        Schema::table('listening_statistics', function (Blueprint $table) {
            $table->unsignedBigInteger('book_id')->nullable()->change();
            $table->string('title')->nullable()->after('book_id');
            $table->string('author')->nullable()->after('title');
            $table->string('genre')->nullable()->after('author');

            $table->index(['genre', 'listening_date']);
        });
    }

    public function down(): void
    {
        Schema::table('listening_statistics', function (Blueprint $table) {
            $table->dropIndex(['genre', 'listening_date']);
            $table->dropColumn(['title', 'author', 'genre']);
            $table->unsignedBigInteger('book_id')->nullable(false)->change();
        });
    }
};

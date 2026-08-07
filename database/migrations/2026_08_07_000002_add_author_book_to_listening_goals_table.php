<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Lite has no book/author/series tables — goals are scoped by the same
     * client-supplied title/author strings listening_statistics already uses.
     * There is no series concept at all on lite (listening_statistics has no
     * series column), so series-scoped goals are not supported here.
     */
    public function up(): void
    {
        Schema::table('listening_goals', function (Blueprint $table): void {
            $table->string('author_name')->nullable()->after('playlist_id');
            $table->string('book_title')->nullable()->after('author_name');
            $table->string('book_author')->nullable()->after('book_title');
        });
    }

    public function down(): void
    {
        Schema::table('listening_goals', function (Blueprint $table): void {
            $table->dropColumn(['author_name', 'book_title', 'book_author']);
        });
    }
};

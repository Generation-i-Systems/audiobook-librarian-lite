<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Lite has no book/author tables, so tags are keyed by the same raw
     * title+author strings listening_statistics/listening_goals already
     * use. Same three-tier scope model as the full server: system
     * (admin-only, everyone sees it), group (members-only), user (private).
     * owner_key ("system" | "group:{id}" | "user:{id}") makes uniqueness
     * per (book_title, book_author, owner_key) independent of nullability.
     */
    public function up(): void
    {
        Schema::create('book_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('book_title');
            $table->string('book_author')->default('');
            $table->string('scope', 20)->default('user');
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('owner_key');
            $table->json('tags');
            $table->timestamps();

            $table->unique(['book_title', 'book_author', 'owner_key']);
            $table->index(['book_title', 'book_author', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_tags');
    }
};

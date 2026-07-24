<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('icon')->nullable();
            $table->string('icon_path')->nullable();
            $table->string('image_url')->nullable();
            $table->string('category');
            $table->string('tier')->default('bronze');
            $table->integer('points')->default(0);
            $table->json('criteria');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_repeatable')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'tier']);
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * These columns already exist on the live database (added out-of-band, not
     * via a tracked migration) but are missing from fresh/test databases, so
     * every column add is guarded with a hasColumn check.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'google_id')) {
                $table->string('google_id')->nullable()->unique()->after('password');
            }
            if (!Schema::hasColumn('users', 'facebook_id')) {
                $table->string('facebook_id')->nullable()->unique()->after('google_id');
            }
            if (!Schema::hasColumn('users', 'apple_id')) {
                $table->string('apple_id')->nullable()->unique()->after('facebook_id');
            }
            if (!Schema::hasColumn('users', 'discord_id')) {
                $table->string('discord_id')->nullable()->unique()->after('apple_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'facebook_id', 'apple_id', 'discord_id']);
        });
    }
};

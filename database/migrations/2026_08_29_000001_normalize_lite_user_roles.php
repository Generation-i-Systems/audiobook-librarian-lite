<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Lite has only unverified, user, and admin roles.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'super-admin')->update(['role' => 'admin']);

        DB::table('users')
            ->whereNotIn('role', ['unverified', 'user', 'admin'])
            ->orWhereNull('role')
            ->update(['role' => 'user']);
    }

    public function down(): void
    {
        // Legacy source-mode roles cannot be reconstructed safely.
    }
};

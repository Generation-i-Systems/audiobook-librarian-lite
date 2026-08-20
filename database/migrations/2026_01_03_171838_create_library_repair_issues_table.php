<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Library repair belongs to the full server and is not a Lite feature.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Never drop a legacy table during rollback.
    }
};

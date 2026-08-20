<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Positions are populated by the active position-sync API, not a legacy table copy.
    }

    public function down(): void
    {
        // Never delete position data during rollback.
    }
};

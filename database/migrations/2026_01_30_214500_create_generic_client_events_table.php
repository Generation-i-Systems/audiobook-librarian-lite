<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Generic client events were superseded by listening_events and are not part of Lite.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Never drop a legacy table during rollback.
    }
};

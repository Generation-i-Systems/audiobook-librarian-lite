<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Legacy book_progress data is outside Lite's event-sourced synchronization model.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Never delete historical events during rollback.
    }
};

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function testFreshLiteDatabaseExcludesRetiredServerTables(): void
    {
        $this->assertTrue(Schema::hasTable('listening_events'));
        $this->assertTrue(Schema::hasTable('book_positions'));
        $this->assertTrue(Schema::hasTable('book_progress'));
        $this->assertTrue(Schema::hasTable('client_books'));
        $this->assertTrue(Schema::hasTable('badges'));
        $this->assertTrue(Schema::hasTable('user_badges'));

        $this->assertFalse(Schema::hasTable('generic_client_events'));
        $this->assertFalse(Schema::hasTable('library_repair_issues'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserIsAdminAttributeTest extends TestCase
{
    #[Test]
    public function testIsAdminTrueForAdminRole(): void
    {
        $user = new User();
        $user->role = 'admin';

        $this->assertTrue($user->is_admin);
    }

    #[Test]
    public function testIsAdminTrueForAdminRoleThroughAccessor(): void
    {
        $user = new User();
        $user->role = 'admin';

        $this->assertTrue($user->is_admin);
    }

    #[Test]
    public function testIsAdminFalseForUserRole(): void
    {
        $user = new User();
        $user->role = 'user';

        $this->assertFalse($user->is_admin);
    }
}

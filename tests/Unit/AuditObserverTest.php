<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_user_attributes_are_not_written_to_audit(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $log = AuditLog::where('auditable_type', User::class)->latest()->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
        $this->assertArrayNotHasKey('remember_token', $log->new_values ?? []);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class UserModelFieldsTest extends TestCase
{
    public function test_new_user_has_is_active_true_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_last_login_at_can_be_stored_and_retrieved(): void
    {
        $user = User::factory()->create();
        $loginTime = now()->subHour();

        $user->last_login_at = $loginTime;
        $user->save();

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertEquals($loginTime->timestamp, $user->last_login_at->timestamp);
    }

    public function test_is_active_can_be_set_to_false(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->assertFalse($user->is_active);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);
    }
}
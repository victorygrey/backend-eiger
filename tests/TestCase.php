<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Whether to automatically authenticate a default SuperAdmin for tests.
     */
    protected bool $autoAuthenticate = true;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pim.allow_static_inbound_token' => true]);

        if ($this->autoAuthenticate && in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive(static::class))) {
            $user = User::factory()->superadmin()->create();
            $this->actingAs($user);
        }
    }

    /**
     * Helper to create and authenticate a SuperAdmin user.
     */
    protected function authenticateSuperAdmin(array $attributes = []): User
    {
        $user = User::factory()->superadmin()->create($attributes);
        $this->actingAs($user);
        return $user;
    }

    /**
     * Helper to create and authenticate a standard Admin user.
     */
    protected function authenticateAdmin(array $attributes = []): User
    {
        $user = User::factory()->admin()->create($attributes);
        $this->actingAs($user);
        return $user;
    }
}

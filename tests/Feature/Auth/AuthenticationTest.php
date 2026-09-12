<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $autoAuthenticate = false;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('EIGER CMS');
        $response->assertSee('Masuk ke Sistem');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'email'     => 'test@eigeradventure.com',
            'password'  => Hash::make('secret123'),
            'role'      => 'superadmin',
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'email'    => 'test@eigeradventure.com',
            'password' => 'secret123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('admin.dashboard'));

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
    }

    public function test_users_can_authenticate_using_username_role(): void
    {
        $user = User::factory()->create([
            'email'     => 'superadmin@eigeradventure.com',
            'password'  => Hash::make('secret123'),
            'role'      => 'superadmin',
            'is_active' => true,
        ]);

        // Login with just 'superadmin' as username
        $response = $this->post(route('login'), [
            'email'    => 'superadmin',
            'password' => 'secret123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_users_can_authenticate_with_case_insensitivity_and_whitespace(): void
    {
        $user = User::factory()->create([
            'email'     => 'admin@eigeradventure.com',
            'password'  => Hash::make('secret123'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        // Login with uppercase and trailing whitespace
        $response = $this->post(route('login'), [
            'email'    => '  Admin@EigerAdventure.com  ',
            'password' => 'secret123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email'    => 'test@eigeradventure.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->post(route('login'), [
            'email'    => 'test@eigeradventure.com',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        User::factory()->create([
            'email'     => 'inactive@eigeradventure.com',
            'password'  => Hash::make('secret123'),
            'is_active' => false,
        ]);

        $response = $this->post(route('login'), [
            'email'    => 'inactive@eigeradventure.com',
            'password' => 'secret123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }
}

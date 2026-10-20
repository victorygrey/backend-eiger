<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserPasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Manual Account', 'email' => 'manual@example.test',
            'role' => 'admin', 'job_title' => 'Store Operator',
            'password' => 'manual-password-123', 'password_confirmation' => 'manual-password-123',
        ], $overrides);
    }

    public function test_create_requires_confirmed_password_job_and_active_role(): void
    {
        $this->get(route('admin.users.index'))->assertOk()->assertSee('Konfirmasi Password')->assertDontSee('Salin Tautan');
        foreach ([
            [['password' => null], 'password'],
            [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            [['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)], 'password'],
            [['password' => str_repeat('é', 37), 'password_confirmation' => str_repeat('é', 37)], 'password'],
            [['password_confirmation' => 'different'], 'password'],
            [['job_title' => null], 'job_title'],
            [['role' => 'missing-role'], 'role'],
        ] as [$overrides, $field]) {
            $this->postJson(route('admin.users.store'), $this->registration($overrides))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseMissing('users', ['email' => 'manual@example.test']);
    }

    public function test_created_user_can_login_directly_without_email_or_setup_link(): void
    {
        Mail::fake();
        $this->post(route('admin.users.store'), $this->registration())
            ->assertRedirect(route('admin.users.index'))->assertSessionMissing('invitation_link');
        $user = User::where('email', 'manual@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('manual-password-123', $user->password));
        $this->assertNotSame('manual-password-123', $user->password);
        $this->assertFalse($user->awaitingSetup());
        $this->assertNull($user->password_setup_token_hash);
        $this->assertArrayNotHasKey('password', $user->toArray());
        Mail::assertNothingOutgoing();
        auth()->logout();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'manual-password-123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_blank_password_preserves_hash_and_reset_replaces_password(): void
    {
        $user = User::factory()->admin()->create(['password' => 'original-password', 'remember_token' => 'previous-remember-token']);
        $data = $this->registration(['email' => $user->email, 'password' => '', 'password_confirmation' => '']);
        $oldHash = $user->password;
        $this->put(route('admin.users.update', $user), $data)->assertSessionHasNoErrors();
        $this->assertSame($oldHash, $user->fresh()->password);
        $this->putJson(route('admin.users.update', $user), array_replace($data, ['password_confirmation' => 'orphan-confirmation']))
            ->assertUnprocessable();
        $this->put(route('admin.users.update', $user), array_replace($data, ['password' => 'replacement-password', 'password_confirmation' => 'replacement-password']))
            ->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue(Hash::check('replacement-password', $user->password));
        $this->assertFalse(Hash::check('original-password', $user->password));
        $this->assertNotSame('previous-remember-token', $user->remember_token);
        auth()->logout();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'original-password'])->assertSessionHasErrors('email');
        $this->post(route('login'), ['email' => $user->email, 'password' => 'replacement-password'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_old_pending_user_requires_admin_password_and_old_setup_routes_are_gone(): void
    {
        $user = User::factory()->admin()->create([
            'invited_at' => now(), 'password_setup_token_hash' => hash('sha256', 'old-token'),
            'password_setup_expires_at' => now()->addDay(),
        ]);
        $data = $this->registration(['email' => $user->email, 'password' => null, 'password_confirmation' => null]);
        $this->putJson(route('admin.users.update', $user), $data)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->get('/account/setup/old-token?email='.$user->email)->assertNotFound();
        $this->post('/account/setup/old-token', $this->registration())->assertNotFound();
        $this->post('/admin/users/'.$user->id.'/invitation')->assertNotFound();
        $this->put(route('admin.users.update', $user), $this->registration(['email' => $user->email]))->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertFalse($user->awaitingSetup());
        $this->assertNull($user->password_setup_token_hash);
        $this->assertNull($user->password_setup_expires_at);
    }

    public function test_non_superadmin_cannot_create_or_reset_another_user_password(): void
    {
        $target = User::factory()->admin()->create(['password' => 'unchanged-password']);
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.users.store'), $this->registration())->assertForbidden();
        $this->put(route('admin.users.update', $target), $this->registration(['email' => $target->email]))->assertForbidden();
        $this->assertTrue(Hash::check('unchanged-password', $target->fresh()->password));
    }

    public function test_failed_update_never_flashes_password_back_to_the_form(): void
    {
        $self = auth()->user();
        $this->put(route('admin.users.update', $self), $this->registration(['email' => $self->email]))
            ->assertSessionHasErrors('role')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->post(route('admin.users.store'), $this->registration(['password_confirmation' => 'mismatch']))
            ->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
    }
}

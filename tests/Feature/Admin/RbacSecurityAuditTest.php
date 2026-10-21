<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_and_read_only_users_cannot_use_single_tag_management_api(): void
    {
        auth()->logout();
        $this->postJson('/api/rfid-tags', ['uid' => 'ABCDEF01'])->assertUnauthorized();
        $role = Role::create(['name' => 'RFID Reader', 'slug' => 'rfid-reader', 'permissions' => ['rfid-tags.view'], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => $role->slug]));
        $this->postJson('/api/rfid-tags', ['uid' => 'ABCDEF01'])->assertForbidden();
        $this->assertDatabaseCount('rfid_tags', 0);
        $role->update(['permissions' => ['rfid-tags.view', 'rfid-tags.edit']]);
        $this->postJson('/api/rfid-tags', ['uid' => 'ABCDEF01'])->assertCreated();
    }

    public function test_login_wildcards_and_ambiguous_aliases_cannot_select_an_account(): void
    {
        auth()->logout();
        User::factory()->create(['email' => 'shared@one.test']);
        User::factory()->create(['email' => 'shared@two.test']);
        foreach (['%', '_', 'super%', 'shared'] as $identifier) {
            $this->post(route('login'), ['email' => $identifier, 'password' => 'password'])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
        $this->post(route('login'), ['email' => 'shared@two.test', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs(User::where('email', 'shared@two.test')->firstOrFail());
    }

    public function test_repeated_failed_login_is_throttled_even_when_identifier_alias_changes(): void
    {
        $user = User::factory()->create(['email' => 'limited@example.test', 'name' => 'Limited Account']);
        auth()->logout();
        foreach (['limited@example.test', 'limited', 'Limited Account', 'LIMITED@example.test', 'limited'] as $alias) {
            $this->postJson(route('login'), ['email' => $alias, 'password' => 'wrong-password'])->assertStatus(302);
        }
        $this->postJson(route('login'), ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_old_session_is_rejected_after_password_reset_on_web_and_management_api(): void
    {
        $user = User::factory()->admin()->create();
        foreach (['web', 'api'] as $surface) {
            $oldHash = $user->fresh()->password;
            $user->update(['password' => 'new-secret-'.$surface]);
            $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash]);
            if ($surface === 'web') {
                $this->get(route('admin.products.index'))->assertRedirect(route('login'));
            } else {
                $this->postJson('/api/zones', ['name' => 'Unauthorized stale session'])->assertUnauthorized();
            }
            $this->assertGuest();
        }
        $this->assertDatabaseMissing('zones', ['name' => 'Unauthorized stale session']);
    }

    public function test_password_is_bound_to_session_immediately_after_login(): void
    {
        $user = User::factory()->admin()->create();
        auth()->logout();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHas('password_hash_web', $user->password);
    }

    public function test_profile_validation_does_not_flash_passwords_or_accept_weaker_passwords(): void
    {
        $self = auth()->user();
        $this->put(route('admin.profile.update'), [
            'name' => '', 'email' => $self->email, 'current_password' => 'password',
            'new_password' => 'new-password-secret', 'new_password_confirmation' => 'new-password-secret',
        ])->assertSessionHasErrors('name')->assertSessionMissing('_old_input.new_password')->assertSessionMissing('_old_input.new_password_confirmation');
        $this->putJson(route('admin.profile.update'), [
            'name' => $self->name, 'email' => $self->email, 'current_password' => 'password',
            'new_password' => '123456', 'new_password_confirmation' => '123456',
        ])->assertUnprocessable()->assertJsonValidationErrors('new_password');
        $this->assertTrue(Hash::check('password', $self->fresh()->password));
    }

    public function test_demo_credentials_are_not_exposed_or_created_outside_local_testing(): void
    {
        $this->app['env'] = 'production';
        auth()->logout();
        User::query()->delete();
        $this->get(route('login'))->assertOk()->assertDontSee('Default Login')->assertDontSee('superadmin@eigeradventure.com');
        (new UserSeeder)->run();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_profile_cannot_claim_another_accounts_email_using_different_case(): void
    {
        $root = auth()->user();
        $root->update(['email' => 'Reserved@Example.test']);
        $staff = User::factory()->admin()->create();
        $this->actingAs($staff)->putJson(route('admin.profile.update'), [
            'name' => $staff->name, 'email' => 'RESERVED@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertNotSame('reserved@example.test', $staff->fresh()->email);
    }

    public function test_csrf_is_required_and_valid_csrf_cannot_bypass_permissions(): void
    {
        $role = Role::create(['name' => 'CSRF Reader', 'slug' => 'csrf-reader', 'permissions' => ['rfid-tags.view'], 'is_active' => true]);
        $staff = User::factory()->create(['role' => $role->slug]);
        $this->actingAs($staff)->withSession(['_token' => str_repeat('a', 40)]);
        // The test framework normally bypasses CSRF; exercise real middleware here.
        $this->app['env'] = 'production';
        $this->postJson('/api/rfid-tags', ['uid' => 'ABCDEF01'])->assertStatus(419);
        $this->postJson('/api/rfid-tags', ['uid' => 'ABCDEF01'], ['X-CSRF-TOKEN' => str_repeat('a', 40)])->assertForbidden();
        $this->assertDatabaseCount('rfid_tags', 0);
        $this->app['env'] = 'testing';
    }

    public function test_malformed_identity_inputs_are_rejected_without_server_errors(): void
    {
        $this->postJson(route('admin.roles.store'), ['name' => ['invalid'], 'is_active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('admin.users.store'), ['name' => ['invalid'], 'email' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email']);
        auth()->logout();
        $this->postJson(route('login'), ['email' => ['invalid'], 'password' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }
}

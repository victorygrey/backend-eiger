<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSetupTest extends TestCase
{
    use RefreshDatabase;

    private function invite(): array
    {
        $response = $this->post(route('admin.users.store'), ['name' => 'Test Invitation', 'email' => 'invite@example.test', 'job_title' => 'Store Operator', 'role' => 'admin']);
        $response->assertRedirect()->assertSessionHas('invitation_link');
        $link = session('invitation_link');
        $token = basename(parse_url($link, PHP_URL_PATH));

        return [User::where('email', 'invite@example.test')->firstOrFail(), $link, $token];
    }

    public function test_registration_has_four_fields_and_requires_a_real_active_role(): void
    {
        $this->get(route('admin.users.index'))->assertOk()->assertSee('Jabatan')->assertSee('Buat User')->assertDontSee('Kata Sandi Awal');
        $this->postJson(route('admin.users.store'), ['name' => 'No job', 'email' => 'a@b.test', 'role' => 'admin'])->assertUnprocessable()->assertJsonValidationErrors('job_title');
        $this->postJson(route('admin.users.store'), ['name' => 'No role', 'email' => 'a@b.test', 'role' => 'fake', 'job_title' => 'Test'])->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_one_time_setup_is_hashed_expires_and_allows_login_after_acceptance(): void
    {
        [$user, $link, $token] = $this->invite();
        $this->assertSame(hash('sha256', $token), $user->password_setup_token_hash);
        $this->assertStringNotContainsString($token, json_encode($user->getAttributes()));
        auth()->logout();
        $this->get($link)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Atur Password');
        $post = route('account.setup.store', $token);
        $data = ['email' => $user->email, 'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password'];
        $this->post($post, $data)->assertRedirect(route('login'));
        $user->refresh();
        $this->assertFalse($user->awaitingSetup());
        $this->assertNull($user->password_setup_token_hash);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->get($link)->assertGone();
        $this->postJson($post, $data)->assertUnprocessable();
        $this->post(route('login'), ['email' => $user->email, 'password' => $data['password']])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_replaced_wrong_email_and_inactive_invitations_are_rejected(): void
    {
        [$user, $link] = $this->invite();
        $newLink = app(UserInvitation::class)->issue($user);
        $this->get($link)->assertGone();
        $this->get($newLink)->assertOk();
        $this->get(str_replace('invite%40example.test', 'wrong%40example.test', $newLink))->assertGone();
        $this->travel(25)->hours();
        $this->get($newLink)->assertGone();
        $this->travelBack();
        $user->update(['is_active' => false]);
        $this->get($newLink)->assertGone();
    }

    public function test_pending_user_cannot_login_and_normal_user_cannot_issue_invitations(): void
    {
        [$user] = $this->invite();
        $user->update(['password' => 'known-pending-pass']);
        auth()->logout();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'known-pending-pass'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.users.invitation', $user))->assertForbidden();
        $this->postJson(route('account.setup.store', 'invalid-token'), ['email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable();
    }
}

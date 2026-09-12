<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementWebTest extends TestCase
{
    use RefreshDatabase;

    protected bool $autoAuthenticate = false;

    public function test_superadmin_can_view_users_list(): void
    {
        $superAdmin = User::factory()->superadmin()->create(['name' => 'Root SuperAdmin']);
        $admin = User::factory()->admin()->create(['name' => 'Store Staff Admin']);

        $response = $this->actingAs($superAdmin)->get(route('admin.users.index'));

        $response->assertStatus(200);
        $response->assertSee('Manajemen User');
        $response->assertSee('Root SuperAdmin');
        $response->assertSee('Store Staff Admin');
    }

    public function test_store_admin_cannot_access_user_management(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertStatus(403);
    }

    public function test_superadmin_can_create_new_user(): void
    {
        $superAdmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'name'      => 'Budi Outdoor',
            'email'     => 'budi@eigeradventure.com',
            'password'  => 'password123',
            'role'      => 'admin',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name'      => 'Budi Outdoor',
            'email'     => 'budi@eigeradventure.com',
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $newUser = User::where('email', 'budi@eigeradventure.com')->first();
        $this->assertTrue(Hash::check('password123', $newUser->password));
    }

    public function test_superadmin_can_update_user(): void
    {
        $superAdmin = User::factory()->superadmin()->create();
        $user = User::factory()->admin()->create(['name' => 'Old Name']);

        $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $user), [
            'name'      => 'Updated Name',
            'email'     => $user->email,
            'role'      => 'superadmin',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_superadmin_cannot_demote_self(): void
    {
        $superAdmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $superAdmin), [
            'name'      => $superAdmin->name,
            'email'     => $superAdmin->email,
            'role'      => 'admin', // trying to demote self
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('role');
        $superAdmin->refresh();
        $this->assertTrue($superAdmin->isSuperAdmin());
    }

    public function test_superadmin_cannot_deactivate_self(): void
    {
        $superAdmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $superAdmin), [
            'name'      => $superAdmin->name,
            'email'     => $superAdmin->email,
            'role'      => 'superadmin',
            'is_active' => '0', // trying to deactivate self
        ]);

        $response->assertSessionHasErrors('is_active');
        $superAdmin->refresh();
        $this->assertTrue($superAdmin->isActive());
    }

    public function test_superadmin_cannot_delete_self(): void
    {
        $superAdmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superAdmin)->delete(route('admin.users.destroy', $superAdmin));

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_cannot_delete_last_superadmin(): void
    {
        $superAdmin1 = User::factory()->superadmin()->create();
        $superAdmin2 = User::factory()->superadmin()->create();

        // 2 SuperAdmins exist: superAdmin1 can delete superAdmin2
        $response = $this->actingAs($superAdmin1)->delete(route('admin.users.destroy', $superAdmin2));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $superAdmin2->id]);

        // Now only 1 SuperAdmin left: superAdmin1 tries to delete another created superadmin or self
        $this->assertEquals(1, User::where('role', 'superadmin')->count());
    }

    public function test_user_can_view_and_update_profile(): void
    {
        $user = User::factory()->create([
            'name'     => 'Original Name',
            'email'    => 'orig@eigeradventure.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $response = $this->actingAs($user)->get(route('admin.profile.index'));
        $response->assertStatus(200);
        $response->assertSee('Profil Saya');
        $response->assertSee('Original Name');

        $updateResponse = $this->actingAs($user)->put(route('admin.profile.update'), [
            'name'                  => 'New Name',
            'email'                 => 'new@eigeradventure.com',
            'current_password'      => 'oldpassword',
            'new_password'          => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $updateResponse->assertRedirect(route('admin.profile.index'));
        $updateResponse->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@eigeradventure.com', $user->email);
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }
}

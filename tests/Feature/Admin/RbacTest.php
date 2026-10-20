<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\RfidTag;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use App\Support\CmsPermissions;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions): User
    {
        $role = Role::create(['name' => 'Test Reader', 'slug' => 'test-reader', 'permissions' => CmsPermissions::normalize($permissions), 'is_active' => true]);

        return User::factory()->create(['role' => $role->slug, 'job_title' => 'Tester']);
    }

    public function test_superadmin_can_create_review_and_update_a_custom_role(): void
    {
        $this->get(route('admin.roles.index'))->assertOk()->assertSee('Buat Role')->assertSee('Edit / Tambah')->assertSee('Export');
        $this->post(route('admin.roles.store'), ['name' => 'Catalog Manager', 'is_active' => true, 'permissions' => ['products.edit']])->assertRedirect()->assertSessionHasNoErrors();
        $role = Role::where('name', 'Catalog Manager')->firstOrFail();
        $this->assertEqualsCanonicalizing(['products.view', 'products.edit'], $role->permissions);
        $this->put(route('admin.roles.update', $role), ['name' => 'Catalog Viewer', 'is_active' => true, 'permissions' => ['products.view']])->assertRedirect();
        $this->assertSame(['products.view'], $role->fresh()->permissions);
        $this->postJson(route('admin.roles.store'), ['name' => 'catalog viewer', 'is_active' => true])->assertUnprocessable();
        $this->postJson(route('admin.roles.store'), ['name' => 'Forged', 'is_active' => true, 'permissions' => ['users.edit']])->assertUnprocessable();
    }

    public function test_role_management_remains_superadmin_only_and_system_roles_are_protected(): void
    {
        $this->put(route('admin.roles.update', Role::where('slug', 'superadmin')->first()), ['name' => 'Hacked', 'is_active' => true])->assertForbidden();
        $this->delete(route('admin.roles.destroy', Role::where('slug', 'admin')->first()))->assertForbidden();
        $staff = $this->staff(['products.view']);
        $this->actingAs($staff)->get(route('admin.roles.index'))->assertForbidden();
        $this->post(route('admin.roles.store'), ['name' => 'Escalation', 'is_active' => true])->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->post(route('admin.users.store'), ['role' => 'superadmin'])->assertForbidden();
    }

    public function test_read_only_user_cannot_bypass_permissions_by_url_method_or_export(): void
    {
        $staff = $this->staff(['products.view', 'zones.view']);
        $product = Product::factory()->create();
        $response = $this->actingAs($staff)->get(route('admin.products.index'))->assertOk()->assertSee('Mode lihat');
        $response->assertDontSee(route('admin.roles.index'), false)->assertDontSee(route('admin.fit-and-go.index'), false);
        $this->get(route('admin.zones.create'))->assertForbidden();
        $this->patchJson(route('admin.products.channel-lock.update'), ['locked' => false])->assertForbidden();
        $this->deleteJson(route('admin.products.destroy', $product))->assertForbidden();
        $this->get(route('admin.products.export'))->assertForbidden();
        $this->get(route('admin.integrations.index'))->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_permissions_revoke_immediately_and_inactive_sessions_are_closed(): void
    {
        $staff = $this->staff(['products.view']);
        $this->actingAs($staff)->get(route('admin.products.index'))->assertOk();
        Role::where('slug', $staff->role)->update(['permissions' => []]);
        $this->get(route('admin.products.index'))->assertForbidden();
        Role::where('slug', $staff->role)->update(['is_active' => false]);
        $this->get(route('admin.profile.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_export_permission_has_real_csv_output_without_formula_execution(): void
    {
        $staff = $this->staff(['products.export']);
        Product::factory()->create(['name' => '=SUM(1,2)', 'sku' => '910123456']);
        $csv = $this->actingAs($staff)->get(route('admin.products.export'))->assertOk()->assertDownload()->streamedContent();
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringContainsString('910123456', $csv);
        $this->post(route('admin.zones.store'), ['name' => 'Forbidden'])->assertForbidden();
        $root = User::where('role', 'superadmin')->firstOrFail();
        foreach (['rfid-tags', 'print-history', 'sync-logs'] as $module) {
            $this->actingAs($root)->get(route('admin.'.$module.'.export'))->assertOk()->assertDownload();
        }
    }

    public function test_role_in_use_cannot_be_deleted_and_legacy_accounts_are_preserved_by_seeder(): void
    {
        $staff = $this->staff([]);
        $role = Role::where('slug', $staff->role)->firstOrFail();
        $this->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');
        $existing = User::factory()->create(['email' => 'admin@eigeradventure.com', 'name' => 'Renamed Admin', 'password' => 'my-retained-password', 'role' => $role->slug, 'is_active' => false]);
        $this->seed(UserSeeder::class);
        $existing->refresh();
        $this->assertSame('Renamed Admin', $existing->name);
        $this->assertSame($role->slug, $existing->role);
        $this->assertFalse((bool) $existing->is_active);
        $this->assertTrue(Hash::check('my-retained-password', $existing->password));
    }

    public function test_login_lands_on_an_allowed_module_and_profile_cannot_assign_role(): void
    {
        $staff = $this->staff(['zones.view']);
        auth()->logout();
        $this->post(route('login'), ['email' => $staff->email, 'password' => 'password'])->assertRedirect(route('admin.zones.index'));
        $this->put(route('admin.profile.update'), ['name' => $staff->name, 'email' => $staff->email, 'role' => 'superadmin', 'is_active' => true])->assertRedirect();
        $this->assertSame('test-reader', $staff->fresh()->role);
    }

    public function test_legacy_management_api_does_not_bypass_cms_permissions(): void
    {
        $staff = $this->staff(['zones.view', 'rfid-tags.view']);
        $zone = Zone::create(['name' => 'Existing Zone']);
        $tag = RfidTag::create(['uid' => 'ABCDEF01']);
        $this->actingAs($staff)->postJson('/api/zones', ['name' => 'Forbidden Zone'])->assertForbidden();
        $this->deleteJson('/api/zones/'.$zone->id)->assertForbidden();
        $this->deleteJson('/api/rfid-tags/'.$tag->id)->assertForbidden();
        $this->postJson('/api/sync/care')->assertForbidden();
        auth()->logout();
        $this->postJson('/api/zones', ['name' => 'Unauthenticated'])->assertUnauthorized();
        $this->getJson('/api/v1/table-expedition/standby')->assertOk();
        $this->postJson('/api/rfid-tags/resolve', ['uids' => ['ABCDEF01']])->assertOk();
        $this->assertDatabaseHas('zones', ['id' => $zone->id]);
        $this->assertDatabaseHas('rfid_tags', ['id' => $tag->id]);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\RfidTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfidTagWebTest extends TestCase
{
    use RefreshDatabase;

    protected bool $autoAuthenticate = false;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'      => 'superadmin',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_rfid_tags_page(): void
    {
        $response = $this->get(route('admin.rfid-tags.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_rfid_tags_index(): void
    {
        $tag = RfidTag::create([
            'uid' => 'E280116060000204AABBCCDD',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.rfid-tags.index'));

        $response->assertStatus(200);
        $response->assertSee('Manajemen RFID Tag');
        $response->assertSee('UID RFID');
        $response->assertSee('Tanggal Terscan');
        $response->assertSee('E280116060000204AABBCCDD');
        $response->assertSee($tag->created_at->format('d M Y'));
    }

    public function test_search_filters_rfid_tags_by_uid(): void
    {
        RfidTag::create(['uid' => 'E28011606000020411111111']);
        RfidTag::create(['uid' => 'E28011606000020422222222']);

        $response = $this->actingAs($this->admin)->get(route('admin.rfid-tags.index', [
            'search' => '11111111',
        ]));

        $response->assertStatus(200);
        $response->assertSee('E28011606000020411111111');
        $response->assertDontSee('E28011606000020422222222');
    }

    public function test_user_can_view_create_rfid_tag_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.rfid-tags.create'));

        $response->assertStatus(200);
        $response->assertSee('Form RFID Tag Baru');
    }

    public function test_user_can_store_new_rfid_tag(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.rfid-tags.store'), [
            'uid' => 'E28011606000020499998888',
        ]);

        $response->assertRedirect(route('admin.rfid-tags.index'));
        $this->assertDatabaseHas('rfid_tags', [
            'uid' => 'E28011606000020499998888',
        ]);
    }
}

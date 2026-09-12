<?php

namespace Tests\Feature\Admin;

use App\Models\LedAmbienceItem;
use App\Models\LedAmbienceScene;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedAmbienceWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic scene
        LedAmbienceScene::create([
            'name'           => 'Idle Nature Ambience',
            'scene_type'     => 'idle',
            'video_url'      => 'https://example.com/idle.mp4',
            'audio_url'      => 'https://example.com/idle.mp3',
            'lighting_color' => '#e8500a',
            'sort_order'     => 0,
            'is_active'      => true,
        ]);
    }

    public function test_led_ambience_dashboard_loads_rfid_tab(): void
    {
        $product = Product::factory()->create(['name' => 'EIGER Torrent Jacket', 'is_discontinued' => false]);
        LedAmbienceItem::create([
            'rfid_tag'      => 'E28011606000020468900010',
            'product_id'    => $product->id,
            'activity_slug' => 'mountaineering',
            'is_active'     => true,
        ]);

        $response = $this->get(route('admin.led-ambience.index', ['tab' => 'rfid']));

        $response->assertStatus(200);
        $response->assertSee('LED Ambience');
        $response->assertSee('E28011606000020468900010');
        $response->assertSee('EIGER Torrent Jacket');
    }

    public function test_led_ambience_dashboard_loads_scenes_tab(): void
    {
        LedAmbienceScene::create([
            'name'           => 'Mountaineering Storm',
            'scene_type'     => 'active',
            'activity_slug'  => 'mountaineering',
            'lighting_color' => '#0284c7',
            'sort_order'     => 1,
            'is_active'      => true,
        ]);

        $response = $this->get(route('admin.led-ambience.index', ['tab' => 'scenes']));

        $response->assertStatus(200);
        $response->assertSee('Mountaineering Storm');
        $response->assertSee('Idle Nature Ambience');
    }

    public function test_can_create_rfid_mapping(): void
    {
        $product = Product::factory()->create(['is_discontinued' => false]);

        $response = $this->post(route('admin.led-ambience.rfid-items.store'), [
            'rfid_tag'      => 'e28011606000020468900099',
            'product_id'    => $product->id,
            'activity_slug' => 'hiking',
            'notes'         => 'Sample Sepatu Hiking',
            'is_active'     => 1,
        ]);

        $response->assertRedirect(route('admin.led-ambience.index', ['tab' => 'rfid']));
        $this->assertDatabaseHas('led_ambience_items', [
            'rfid_tag'      => 'E28011606000020468900099', // uppercase
            'product_id'    => $product->id,
            'activity_slug' => 'hiking',
        ]);
    }

    public function test_can_update_and_delete_rfid_mapping(): void
    {
        $product1 = Product::factory()->create(['is_discontinued' => false]);
        $product2 = Product::factory()->create(['is_discontinued' => false]);

        $item = LedAmbienceItem::create([
            'rfid_tag'      => 'E28011606000020468900055',
            'product_id'    => $product1->id,
            'activity_slug' => 'camping',
            'is_active'     => true,
        ]);

        // Update
        $updateResp = $this->put(route('admin.led-ambience.rfid-items.update', $item), [
            'rfid_tag'      => 'E28011606000020468900055',
            'product_id'    => $product2->id,
            'activity_slug' => 'riding',
            'notes'         => 'Updated note',
            'is_active'     => 1,
        ]);

        $updateResp->assertRedirect(route('admin.led-ambience.index', ['tab' => 'rfid']));
        $this->assertDatabaseHas('led_ambience_items', [
            'id'            => $item->id,
            'product_id'    => $product2->id,
            'activity_slug' => 'riding',
        ]);

        // Delete
        $deleteResp = $this->delete(route('admin.led-ambience.rfid-items.destroy', $item));
        $deleteResp->assertRedirect(route('admin.led-ambience.index', ['tab' => 'rfid']));
        $this->assertDatabaseMissing('led_ambience_items', [
            'id' => $item->id,
        ]);
    }

    public function test_can_create_update_and_delete_scene(): void
    {
        // Create
        $createResp = $this->post(route('admin.led-ambience.scenes.store'), [
            'name'           => 'Tropical Waterfall',
            'scene_type'     => 'active',
            'activity_slug'  => 'hiking',
            'video_url'      => 'https://example.com/waterfall.mp4',
            'audio_url'      => 'https://example.com/waterfall.mp3',
            'lighting_color' => '#16a34a',
            'description'    => 'Suara air terjun sejuk',
            'sort_order'     => 3,
            'is_active'      => 1,
        ]);

        $createResp->assertRedirect(route('admin.led-ambience.index', ['tab' => 'scenes']));
        $scene = LedAmbienceScene::where('name', 'Tropical Waterfall')->first();
        $this->assertNotNull($scene);

        // Update
        $updateResp = $this->put(route('admin.led-ambience.scenes.update', $scene), [
            'name'           => 'Tropical Waterfall & Rain',
            'scene_type'     => 'active',
            'activity_slug'  => 'hiking',
            'video_url'      => 'https://example.com/waterfall.mp4',
            'audio_url'      => 'https://example.com/rain.mp3',
            'lighting_color' => '#0d9488',
            'description'    => 'Suara hujan lebat di hutan',
            'sort_order'     => 4,
            'is_active'      => 1,
        ]);

        $updateResp->assertRedirect(route('admin.led-ambience.index', ['tab' => 'scenes']));
        $this->assertDatabaseHas('led_ambience_scenes', [
            'id'   => $scene->id,
            'name' => 'Tropical Waterfall & Rain',
        ]);

        // Delete
        $deleteResp = $this->delete(route('admin.led-ambience.scenes.destroy', $scene));
        $deleteResp->assertRedirect(route('admin.led-ambience.index', ['tab' => 'scenes']));
        $this->assertDatabaseMissing('led_ambience_scenes', [
            'id' => $scene->id,
        ]);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\LedAmbienceItem;
use App\Models\LedAmbienceScene;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedAmbienceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Idle scene
        LedAmbienceScene::create([
            'name'           => 'Idle Loop',
            'scene_type'     => 'idle',
            'video_url'      => 'https://example.com/idle.mp4',
            'audio_url'      => 'https://example.com/idle.mp3',
            'lighting_color' => '#e8500a',
            'sort_order'     => 0,
            'is_active'      => true,
        ]);

        // Active scene
        LedAmbienceScene::create([
            'name'           => 'Mountaineering Extreme',
            'scene_type'     => 'active',
            'activity_slug'  => 'mountaineering',
            'video_url'      => 'https://example.com/mountain.mp4',
            'audio_url'      => 'https://example.com/wind.mp3',
            'lighting_color' => '#0284c7',
            'sort_order'     => 1,
            'is_active'      => true,
        ]);
    }

    public function test_api_can_get_idle_scene(): void
    {
        $response = $this->getJson('/api/v1/led-ambience/idle');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data'   => [
                'name'           => 'Idle Loop',
                'scene_type'     => 'idle',
                'video_url'      => 'https://example.com/idle.mp4',
                'lighting_color' => '#e8500a',
            ],
        ]);
    }

    public function test_api_can_get_scenes(): void
    {
        $response = $this->getJson('/api/v1/led-ambience/scenes');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'count',
            'data' => [
                '*' => ['id', 'name', 'scene_type', 'lighting_color', 'video_url', 'audio_url'],
            ],
        ]);
        $response->assertJsonFragment(['name' => 'Mountaineering Extreme']);
    }

    public function test_api_trigger_with_mapped_rfid(): void
    {
        $product = Product::factory()->create([
            'name'            => 'EIGER Expedition Parka',
            'is_discontinued' => false,
        ]);

        $item = LedAmbienceItem::create([
            'rfid_tag'      => 'E28011606000020468900111',
            'product_id'    => $product->id,
            'activity_slug' => 'mountaineering',
            'is_active'     => true,
        ]);

        $response = $this->postJson('/api/v1/led-ambience/trigger', [
            'rfid_tag' => 'E28011606000020468900111',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status'  => 'success',
            'matched' => true,
            'data'    => [
                'rfid_tag'      => 'E28011606000020468900111',
                'activity_slug' => 'mountaineering',
                'product'       => [
                    'id'   => $product->id,
                    'name' => 'EIGER Expedition Parka',
                ],
                'scene'         => [
                    'name'           => 'Mountaineering Extreme',
                    'lighting_color' => '#0284c7',
                ],
            ],
        ]);

        $item->refresh();
        $this->assertNotNull($item->last_scanned_at);
    }

    public function test_api_trigger_with_unregistered_rfid(): void
    {
        $response = $this->postJson('/api/v1/led-ambience/trigger', [
            'rfid_tag' => 'UNKNOWN_TAG_9999',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'status'  => 'not_found',
            'matched' => false,
        ]);
    }

    public function test_api_item_lost_returns_idle_scene(): void
    {
        $response = $this->postJson('/api/v1/led-ambience/item-lost');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'event'  => 'item_lost',
            'data'   => [
                'scene' => [
                    'name'       => 'Idle Loop',
                    'scene_type' => 'idle',
                ],
            ],
        ]);
    }

    public function test_api_status(): void
    {
        $response = $this->getJson('/api/v1/led-ambience/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'total_rfid_items',
                'active_rfid_items',
                'total_scenes',
                'idle_configured',
                'server_time',
            ],
        ]);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\LedAmbienceItem;
use App\Models\LedAmbienceTemplate;
use App\Models\Product;
use App\Models\ProductActivity;
use App\Models\RfidTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedAmbienceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_idle_and_scenes_return_fixed_global_templates(): void
    {
        $idle = LedAmbienceTemplate::where('template_key', 'idle')->firstOrFail();
        $idle->update([
            'video_url' => '/api/pim-media/led-ambience/videos/idle/'.str_repeat('a', 64).'.mp4',
            'audio_url' => '/api/pim-media/led-ambience/audio/idle/'.str_repeat('b', 64).'.mp3',
        ]);

        $this->getJson('/api/v1/led-ambience/idle')
            ->assertOk()
            ->assertJsonPath('data.template_key', 'idle')
            ->assertJsonPath('data.scene_type', 'idle')
            ->assertJsonPath('data.video_url', url($idle->video_url));

        $this->getJson('/api/v1/led-ambience/scenes')
            ->assertOk()
            ->assertJsonPath('count', 5)
            ->assertJsonPath('data.1.template_key', 'mountaineering');
    }

    public function test_trigger_uses_dominant_activity_group_template(): void
    {
        $product = Product::factory()->create(['name' => 'EIGER Multi Activity Jacket']);
        foreach ([
            ['name' => 'Climbing', 'selected_rating' => 3, 'rating' => 5],
            ['name' => 'Hiking', 'selected_rating' => 4, 'rating' => 5],
            ['name' => 'Summit', 'selected_rating' => 5, 'rating' => 5],
            ['name' => 'Travelling', 'selected_rating' => 5, 'rating' => 5],
        ] as $index => $activity) {
            ProductActivity::create($activity + [
                'product_id' => $product->id,
                'is_selected' => true,
                'sort_order' => $index,
            ]);
        }

        $uid = 'E28011606000020468900111';
        RfidTag::create(['uid' => $uid, 'product_id' => $product->id]);
        $item = LedAmbienceItem::create(['rfid_tag' => $uid, 'product_id' => $product->id, 'is_active' => true]);
        $template = LedAmbienceTemplate::where('template_key', 'mountaineering')->firstOrFail();
        $template->update([
            'video_url' => 'https://example.com/mountain.mp4',
            'audio_url' => 'https://example.com/wind.mp3',
        ]);

        $this->postJson('/api/v1/led-ambience/trigger', ['rfid_tag' => $uid])
            ->assertOk()
            ->assertJsonPath('source', 'dominant_activity_template')
            ->assertJsonPath('data.activity_group.key', 'mountaineering')
            ->assertJsonPath('data.activity_group.votes', 3)
            ->assertJsonPath('data.activity_group.counts.lifestyle', 1)
            ->assertJsonPath('data.scene.template_key', 'mountaineering')
            ->assertJsonPath('data.scene.video_url', 'https://example.com/mountain.mp4')
            ->assertJsonPath('data.fallback_to_idle', false);

        $this->assertNotNull($item->fresh()->last_scanned_at);
        $this->assertNotNull(RfidTag::where('uid', $uid)->firstOrFail()->last_scanned_at);
    }

    public function test_tied_vote_uses_normalized_selected_rating_then_fixed_priority(): void
    {
        $product = Product::factory()->create();
        ProductActivity::create([
            'product_id' => $product->id, 'name' => 'Hiking', 'selected_rating' => 2, 'rating' => 5,
            'is_selected' => true, 'sort_order' => 0,
        ]);
        ProductActivity::create([
            'product_id' => $product->id, 'name' => 'Travelling', 'selected_rating' => 5, 'rating' => 5,
            'is_selected' => true, 'sort_order' => 1,
        ]);
        $uid = 'E28011606000020468900222';
        RfidTag::create(['uid' => $uid, 'product_id' => $product->id]);
        LedAmbienceItem::create(['rfid_tag' => $uid, 'product_id' => $product->id, 'is_active' => true]);

        $this->postJson('/api/v1/led-ambience/trigger', ['rfid_tag' => $uid])
            ->assertOk()
            ->assertJsonPath('data.activity_group.key', 'lifestyle')
            ->assertJsonPath('data.scene.template_key', 'lifestyle');
    }

    public function test_unknown_activity_falls_back_to_idle(): void
    {
        $product = Product::factory()->create();
        ProductActivity::create([
            'product_id' => $product->id, 'name' => 'Water Sports', 'is_selected' => true, 'sort_order' => 0,
        ]);
        $uid = 'E28011606000020468900333';
        RfidTag::create(['uid' => $uid, 'product_id' => $product->id]);
        LedAmbienceItem::create(['rfid_tag' => $uid, 'product_id' => $product->id, 'is_active' => true]);

        $this->postJson('/api/v1/led-ambience/trigger', ['rfid_tag' => $uid])
            ->assertOk()
            ->assertJsonPath('data.activity_group', null)
            ->assertJsonPath('data.fallback_to_idle', true)
            ->assertJsonPath('data.scene.template_key', 'idle');
    }

    public function test_inactive_or_unknown_rfid_returns_404_and_item_lost_uses_idle(): void
    {
        $product = Product::factory()->create();
        $uid = 'E28011606000020468900444';
        RfidTag::create(['uid' => $uid, 'product_id' => $product->id]);
        LedAmbienceItem::create(['rfid_tag' => $uid, 'product_id' => $product->id, 'is_active' => false]);

        $this->postJson('/api/v1/led-ambience/trigger', ['rfid_tag' => $uid])->assertNotFound();
        $this->postJson('/api/v1/led-ambience/trigger', ['rfid_tag' => 'UNKNOWN'])->assertNotFound();
        $this->postJson('/api/v1/led-ambience/item-lost')
            ->assertOk()
            ->assertJsonPath('data.scene.template_key', 'idle');
    }

    public function test_status_reports_global_template_readiness(): void
    {
        LedAmbienceTemplate::where('template_key', 'riding')->update(['is_active' => false]);
        LedAmbienceTemplate::where('template_key', 'mountaineering')->update(['video_url' => 'https://example.com/a.mp4']);

        $this->getJson('/api/v1/led-ambience/status')
            ->assertOk()
            ->assertJsonPath('data.total_templates', 5)
            ->assertJsonPath('data.active_templates', 4)
            ->assertJsonPath('data.configured_video_templates', 1)
            ->assertJsonPath('data.idle_configured', true);
    }
}

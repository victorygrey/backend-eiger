<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\RfidTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfidTagWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_reports_mapped_unassigned_and_unknown_tags_without_writing(): void
    {
        $product = Product::factory()->create();
        RfidTag::create(['uid' => 'E200-0000:0000 0001', 'product_id' => $product->id]);
        RfidTag::create(['uid' => 'E200000000000002', 'product_id' => null]);

        $this->postJson('/api/rfid-tags/resolve', [
            'uids' => ['e200000000000001', 'E200000000000002', 'E200000000000003'],
        ])->assertOk()
            ->assertJsonPath('data.0.exists', true)
            ->assertJsonPath('data.0.tag.product.id', $product->id)
            ->assertJsonPath('data.1.exists', true)
            ->assertJsonPath('data.1.tag.product_id', null)
            ->assertJsonPath('data.2.exists', false)
            ->assertJsonPath('data.2.tag', null);

        $this->assertDatabaseCount('rfid_tags', 2);
    }

    public function test_batch_store_only_creates_missing_unassigned_tags_and_is_idempotent(): void
    {
        auth()->logout();
        // Exercise the actual external-scanner contract without test-mode CSRF bypass.
        $this->app['env'] = 'production';
        $product = Product::factory()->create();
        $existing = RfidTag::create([
            'uid' => 'E200-0000:0000 0001',
            'product_id' => $product->id,
        ]);

        $payload = ['uids' => [
            'E200000000000001',
            'e200000000000002',
            'E200000000000002',
        ]];

        $this->postJson('/api/rfid-tags/batch', $payload)
            ->assertCreated()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('existing_count', 1)
            ->assertJsonPath('created.0.uid', 'E200000000000002');

        $this->assertDatabaseHas('rfid_tags', [
            'uid' => 'E200000000000002',
            'product_id' => null,
        ]);
        $this->assertSame($product->id, $existing->fresh()->product_id);

        $this->postJson('/api/rfid-tags/batch', $payload)
            ->assertOk()
            ->assertJsonPath('created_count', 0)
            ->assertJsonPath('existing_count', 2);
    }

    public function test_anonymous_batch_does_not_allow_product_assignment_or_channel_activation(): void
    {
        auth()->logout();
        $product = Product::factory()->create();
        $this->postJson('/api/rfid-tags/batch', [
            'uids' => ['E200000000000005'],
            'product_id' => $product->id,
            'led_ambience_active' => true,
            'table_expedition_active' => true,
        ])->assertCreated()->assertJsonPath('created.0.product_id', null);
        $this->assertDatabaseHas('rfid_tags', ['uid' => 'E200000000000005', 'product_id' => null]);
        $this->assertDatabaseCount('led_ambience_items', 0);
        $this->assertDatabaseCount('table_expedition_items', 0);
    }

    public function test_anonymous_batch_still_rejects_invalid_or_oversized_uid_lists(): void
    {
        auth()->logout();
        foreach ([[], ['not-a-hex-uid'], array_fill(0, 501, 'ABCDEF01')] as $uids) {
            $this->postJson('/api/rfid-tags/batch', ['uids' => $uids])->assertUnprocessable();
        }
        $this->assertDatabaseCount('rfid_tags', 0);
    }

    public function test_single_store_accepts_an_unassigned_tag(): void
    {
        $this->postJson('/api/rfid-tags', [
            'uid' => 'e200000000000004',
            'product_id' => null,
        ])->assertCreated()
            ->assertJsonPath('data.uid', 'E200000000000004')
            ->assertJsonPath('data.product_id', null);
    }
}

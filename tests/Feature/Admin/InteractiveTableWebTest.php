<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Tablet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InteractiveTableWebTest extends TestCase
{
    use RefreshDatabase;

    private int $tabletSkuSequence = 910700000;

    private function staticTablet(int $slot): Tablet
    {
        return Tablet::where('slug', Tablet::STATIC_SLOTS[$slot]['slug'])->firstOrFail();
    }

    private function tabletProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'sku' => (string) ++$this->tabletSkuSequence,
            'interactive_tablet_active' => true,
            'pim_catalog_active' => true,
            'is_discontinued' => false,
        ], $attributes));
    }

    public function test_interactive_table_index_loads_four_static_devices(): void
    {
        $featured = $this->tabletProduct(['name' => 'EIGER Streamline Daypack']);
        $rec1 = $this->tabletProduct(['name' => 'EIGER Hiking Cap']);
        $rec2 = $this->tabletProduct(['name' => 'EIGER Windbreaker']);
        $tablet = $this->staticTablet(1);
        $tablet->forceFill([
            'location' => 'Lantai 1 - Area Daypack',
            'featured_product_id' => $featured->id,
            'last_seen_at' => now(),
        ])->save();
        $tablet->recommendations()->attach([
            $rec1->id => ['sort_order' => 0],
            $rec2->id => ['sort_order' => 1],
        ]);

        $this->get(route('admin.tablets.index'))
            ->assertOk()
            ->assertSee('4 perangkat statis')
            ->assertSee('Interactive Tablet 1')
            ->assertSee('ID internal: interactive-tablet-1')
            ->assertSee('EIGER Streamline Daypack')
            ->assertSee('2 produk')
            ->assertSee('Online')
            ->assertDontSee('Tambah Perangkat Table / Display')
            ->assertDontSee('title="Hapus"', false);

        $this->assertSame(4, Tablet::whereIn('slug', Tablet::staticSlugs())->count());
    }

    public function test_create_and_delete_routes_are_unavailable(): void
    {
        $this->get('/admin/tablets/create')->assertStatus(405);
        $this->post('/admin/tablets')->assertStatus(405);
        $this->delete('/admin/tablets/'.$this->staticTablet(1)->id)->assertStatus(405);
    }

    public function test_interactive_table_update_saves_ordered_recommendations(): void
    {
        $featured = $this->tabletProduct();
        $rec1 = $this->tabletProduct();
        $rec2 = $this->tabletProduct();
        $tablet = $this->staticTablet(1);

        $this->put(route('admin.tablets.update', $tablet), [
            'location' => 'Ground Floor',
            'featured_product_id' => $featured->id,
            'recommendation_ids' => [$rec2->id, $rec1->id],
            'activation_code' => 'HUB-2026',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $tablet->refresh();
        $this->assertSame($featured->id, $tablet->featured_product_id);
        $this->assertSame('HUB-2026', $tablet->activation_code_encrypted);
        $this->assertNotSame('HUB-2026', $tablet->getRawOriginal('activation_code_encrypted'));
        $recommendations = $tablet->recommendations()->orderBy('sort_order')->get();
        $this->assertSame([$rec2->id, $rec1->id], $recommendations->pluck('id')->all());
    }

    public function test_interactive_table_rejects_products_not_enabled_from_list_product(): void
    {
        $featured = Product::factory()->create([
            'sku' => '910799998',
            'interactive_tablet_active' => false,
            'pim_catalog_active' => true,
            'is_discontinued' => false,
        ]);
        $recommendation = $this->tabletProduct();
        $tablet = $this->staticTablet(1);

        $this->put(route('admin.tablets.update', $tablet), [
            'featured_product_id' => $featured->id,
            'recommendation_ids' => [$recommendation->id],
            'activation_code' => 'INVALID-01',
            'is_active' => 1,
        ])->assertSessionHasErrors('featured_product_id');

        $this->assertNull($tablet->fresh()->featured_product_id);
    }

    public function test_edit_page_locks_identity_and_keeps_configuration_controls(): void
    {
        $featured = $this->tabletProduct(['name' => 'EIGER Avalanche Jacket']);
        $rec = $this->tabletProduct(['name' => 'EIGER Cargo Pants']);
        $tablet = $this->staticTablet(2);
        $tablet->forceFill([
            'featured_product_id' => $featured->id,
            'activation_code_hash' => Hash::make('MID-01'),
            'activation_code_encrypted' => 'MID-01',
        ])->save();
        $tablet->recommendations()->attach($rec->id, ['sort_order' => 0]);

        $this->get(route('admin.tablets.edit', $tablet))
            ->assertOk()
            ->assertSee('Interactive Tablet 2')
            ->assertSee('value="interactive-tablet-2"', false)
            ->assertSee('Identitas perangkat dikunci')
            ->assertSee('readonly', false)
            ->assertDontSee('name="name"', false)
            ->assertDontSee('name="slug"', false)
            ->assertSee('EIGER Avalanche Jacket')
            ->assertSee('EIGER Cargo Pants')
            ->assertSee('value="MID-01"', false)
            ->assertSee('Simulasi Tampilan Layar')
            ->assertSee('Riwayat Versi Konfigurasi');
    }

    public function test_saving_same_visible_tablet_activation_code_does_not_revoke_pairing(): void
    {
        $featured = $this->tabletProduct();
        $recommendation = $this->tabletProduct();
        $tokenHash = Hash::make('tablet-pairing-token');
        $tablet = $this->staticTablet(3);
        $tablet->forceFill([
            'featured_product_id' => $featured->id,
            'activation_code_hash' => Hash::make('VISIBLE-01'),
            'activation_code_lookup_hash' => Tablet::activationCodeLookupHash('VISIBLE-01'),
            'activation_code_encrypted' => 'VISIBLE-01',
            'device_token_hash' => $tokenHash,
        ])->save();
        $tablet->recommendations()->attach($recommendation->id, ['sort_order' => 0]);

        $this->put(route('admin.tablets.update', $tablet), [
            'location' => 'Lantai 3',
            'featured_product_id' => $featured->id,
            'recommendation_ids' => [$recommendation->id],
            'activation_code' => 'VISIBLE-01',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame($tokenHash, $tablet->fresh()->device_token_hash);
    }

    public function test_update_changes_location_and_products_but_not_identity(): void
    {
        $featured = $this->tabletProduct();
        $newFeatured = $this->tabletProduct();
        $rec = $this->tabletProduct();
        $tablet = $this->staticTablet(4);
        $tablet->forceFill(['featured_product_id' => $featured->id])->save();
        $tablet->recommendations()->attach($rec->id, ['sort_order' => 0]);

        $this->put(route('admin.tablets.update', $tablet), [
            'name' => 'Nama Tidak Boleh Berubah',
            'slug' => 'slug-tidak-boleh-berubah',
            'location' => 'Lantai 2',
            'featured_product_id' => $newFeatured->id,
            'recommendation_ids' => [$rec->id],
            'is_active' => 1,
        ])->assertRedirect(route('admin.tablets.edit', $tablet));

        $tablet->refresh();
        $this->assertSame(2, $tablet->config_version);
        $this->assertSame($newFeatured->id, $tablet->featured_product_id);
        $this->assertSame(Tablet::STATIC_SLOTS[4]['name'], $tablet->name);
        $this->assertSame(Tablet::STATIC_SLOTS[4]['slug'], $tablet->slug);
        $this->assertSame('Lantai 2', $tablet->location);
    }
}

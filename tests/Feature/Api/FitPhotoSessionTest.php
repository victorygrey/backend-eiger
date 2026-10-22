<?php

namespace Tests\Feature\Api;

use App\Models\FitAndGoDevice;
use App\Models\PhotoCapture;
use App\Models\Role;
use App\Models\User;
use App\Services\FitPhotoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FitPhotoSessionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $autoAuthenticate = false;

    private FitAndGoDevice $device;

    private string $endpoint = '/api/v1/fit-and-go/kiosks/test-kiosk/sessions';

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('fit-photos');
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(8, 0));
        $this->key = (string) Str::uuid();
        $this->device = FitAndGoDevice::create([
            'name' => 'Test kiosk', 'device_code' => 'test-kiosk', 'is_active' => true,
            'device_token_hash' => Hash::make('test-device-token'),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'image' => UploadedFile::fake()->image('final.png', 108, 192),
            'gender' => 'men', 'activity' => 'hiking', 'consent' => 'accepted',
            'products' => json_encode([['product_id' => 501, 'product_sku' => '910011123', 'color' => 'BLACK', 'category' => 'hat', 'catalog_item_id' => 50101]]),
        ], $overrides);
    }

    private function save(array $overrides = [])
    {
        return $this->withToken('test-device-token')->withHeaders(['Idempotency-Key' => $this->key, 'Accept' => 'application/json'])
            ->post($this->endpoint, $this->payload($overrides));
    }

    public function test_saves_final_photo_privately_with_a_cms_code_and_store_day_expiry(): void
    {
        $receipt = $this->save()->assertCreated()->json('data');
        $this->assertMatchesRegularExpression('/^[A-F0-9]{10}$/', $receipt['code']);
        $this->assertSame('2026-10-22T16:59:59.000000Z', $receipt['expires_at']);
        $capture = PhotoCapture::firstOrFail();
        $this->assertSame(501, $capture->products[0]['product_id']);
        $this->assertSame('image/png', $capture->image_mime);
        Storage::disk('fit-photos')->assertExists([$capture->photo_path, $capture->thumbnail_path]);
        $this->assertDatabaseCount('print_jobs', 0);
        $this->withToken('test-device-token')->getJson($this->endpoint.'/'.$receipt['code'])->assertOk()->assertJsonPath('data.code', $receipt['code']);
        $this->get($this->endpoint.'/'.$receipt['code'].'/image')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_photo_endpoints_require_an_active_paired_device_even_for_legacy_public_kiosks(): void
    {
        $this->postJson($this->endpoint, [])->assertUnauthorized();
        $this->withToken('wrong')->postJson($this->endpoint, [])->assertUnauthorized();
        $this->device->update(['device_token_hash' => null]);
        $this->save()->assertUnauthorized();
        $this->device->update(['device_token_hash' => Hash::make('test-device-token'), 'is_active' => false]);
        $this->save()->assertUnauthorized();
        $this->assertDatabaseCount('photo_captures', 0);
    }

    public function test_retries_return_the_same_code_and_changed_payload_is_rejected(): void
    {
        $first = $this->save()->assertCreated()->json('data');
        $this->save()->assertOk()->assertJsonPath('data.code', $first['code']);
        $this->save(['activity' => 'running'])->assertConflict();
        $this->save(['image' => UploadedFile::fake()->image('other.png', 200, 200)])->assertConflict();
        $this->assertDatabaseCount('photo_captures', 1);
        $this->assertCount(2, Storage::disk('fit-photos')->allFiles());
    }

    public function test_validates_image_contents_metadata_and_consent_before_storing(): void
    {
        $this->save(['image' => UploadedFile::fake()->createWithContent('fake.png', 'not an image')])->assertUnprocessable();
        $this->save(['image' => UploadedFile::fake()->image('big.png')->size(62501)])->assertUnprocessable();
        $this->save(['consent' => 'declined'])->assertUnprocessable();
        $this->save(['products' => 'not-json'])->assertUnprocessable();
        $this->save(['products' => '{}'])->assertUnprocessable();
        $this->key = 'not-uuid';
        $this->save()->assertUnprocessable();
        $this->assertDatabaseCount('photo_captures', 0);
        $this->assertCount(0, Storage::disk('fit-photos')->allFiles());
    }

    public function test_other_kiosks_cannot_read_this_kiosks_receipt_or_photo(): void
    {
        $code = $this->save()->assertCreated()->json('data.code');
        FitAndGoDevice::create(['name' => 'Other', 'device_code' => 'other', 'is_active' => true, 'device_token_hash' => Hash::make('other-token')]);
        $this->withToken('other-token')->getJson('/api/v1/fit-and-go/kiosks/other/sessions/'.$code)->assertNotFound();
        $this->withToken('other-token')->getJson($this->endpoint.'/'.$code.'/image')->assertUnauthorized();
    }

    public function test_expiry_blocks_access_and_purge_removes_images_without_resurrecting_retries(): void
    {
        $code = $this->save()->assertCreated()->json('data.code');
        $capture = PhotoCapture::firstOrFail();
        $paths = [$capture->photo_path, $capture->thumbnail_path];
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(0, 0));
        $this->getJson($this->endpoint.'/'.$code)->assertGone();
        $this->getJson($this->endpoint.'/'.$code.'/image')->assertGone();
        $this->save()->assertGone();
        $this->artisan('fit-photos:purge')->assertSuccessful();
        Storage::disk('fit-photos')->assertMissing($paths);
        $capture->refresh();
        $this->assertNull($capture->products);
        $this->assertNull($capture->photo_path);
        $this->assertNotNull($capture->purged_at);
        $this->save()->assertGone();
        $this->assertDatabaseCount('photo_captures', 1);
    }

    public function test_admin_preview_requires_login_and_displays_private_photo_in_existing_history(): void
    {
        $this->save()->assertCreated();
        $capture = PhotoCapture::firstOrFail();
        $url = route('admin.print-history.image', $capture);
        $this->getJson($url)->assertUnauthorized();
        $this->authenticateSuperAdmin();
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($url.'?thumbnail=1')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('admin.print-history.show', $capture))->assertOk()->assertSee('910011123');
        $this->get(route('admin.print-history.index'))->assertOk()->assertSee($capture->capture_code);
    }

    public function test_storage_failure_rolls_back_the_capture_and_removes_partial_files(): void
    {
        $this->mock(FitPhotoStorage::class)->shouldReceive('thumbnail')->once()->andThrow(new \RuntimeException('Storage unavailable'));
        $this->save()->assertServerError();
        $this->assertDatabaseCount('photo_captures', 0);
        $this->assertCount(0, Storage::disk('fit-photos')->allFiles());
    }

    public function test_final_photo_is_returned_byte_for_byte_for_future_printing(): void
    {
        $image = UploadedFile::fake()->image('full-final.png', 720, 1280);
        $original = file_get_contents($image->getRealPath());
        $receipt = $this->save(['image' => $image])->assertCreated()->json('data');

        $response = $this->get($this->endpoint.'/'.$receipt['code'].'/image')
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($original, $response->streamedContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('print_jobs', 0);
    }

    public function test_nuxt_history_has_authenticated_image_urls_without_private_paths_and_expired_images_are_unavailable(): void
    {
        $this->save()->assertCreated();
        $capture = PhotoCapture::firstOrFail();
        $this->authenticateSuperAdmin();
        $this->withHeader('X-CMS-Interface', 'nuxt');
        $photoUrl = route('admin.print-history.image', $capture);
        $thumbnailUrl = route('admin.print-history.image', ['photoCapture' => $capture, 'thumbnail' => 1]);

        $this->getJson(route('admin.print-history.index'))
            ->assertOk()->assertJsonPath('data.captures.data.0.capture_code', $capture->capture_code)
            ->assertJsonPath('data.captures.data.0.photo_url', $photoUrl)
            ->assertJsonPath('data.captures.data.0.thumbnail_url', $thumbnailUrl)
            ->assertJsonPath('data.captures.data.0.photo_available', true)
            ->assertJsonMissingPath('data.captures.data.0.photo_path')
            ->assertJsonMissingPath('data.captures.data.0.request_hash')
            ->assertJsonMissingPath('data.captures.data.0.idempotency_key');
        $this->getJson(route('admin.print-history.show', $capture))
            ->assertOk()->assertJsonPath('data.photoCapture.photo_url', $photoUrl)
            ->assertJsonPath('data.photoCapture.products.0.product_sku', '910011123');
        // Opting into the new JSON interface must not wrap image bytes in JSON.
        $this->getJson($photoUrl)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->getJson($thumbnailUrl)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(0, 0));
        $this->getJson(route('admin.print-history.show', $capture))
            ->assertOk()->assertJsonPath('data.photoCapture.photo_available', false)
            ->assertJsonPath('data.photoCapture.photo_url', null)
            ->assertJsonPath('data.photoCapture.thumbnail_url', null);
        $this->getJson($photoUrl)->assertGone();
    }

    public function test_history_images_require_the_print_history_view_permission(): void
    {
        $this->save()->assertCreated();
        $capture = PhotoCapture::firstOrFail();
        $role = Role::create(['slug' => 'photo-reader-test', 'name' => 'Photo reader test', 'is_active' => true, 'permissions' => ['products.view']]);
        $this->actingAs(User::factory()->create(['role' => $role->slug]));
        $this->getJson(route('admin.print-history.image', $capture))->assertForbidden();
        $this->getJson(route('admin.print-history.image', ['photoCapture' => $capture, 'thumbnail' => 1]))->assertForbidden();

        $role->update(['permissions' => ['print-history.view']]);
        $this->getJson(route('admin.print-history.image', $capture))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_legacy_import_preserves_the_emailed_code_and_retries_without_overwriting_an_existing_photo(): void
    {
        $this->getJson($this->endpoint.'/capabilities')->assertUnauthorized();
        $this->withToken('test-device-token')->getJson($this->endpoint.'/capabilities')
            ->assertOk()->assertJsonPath('data.preserve_capture_code', true);
        $code = 'ABCDEF1234';
        $this->save(['capture_code' => $code])->assertCreated()->assertJsonPath('data.code', $code);
        $this->save(['capture_code' => $code])->assertOk()->assertJsonPath('data.code', $code);
        $this->save(['capture_code' => 'ABCDEF1235'])->assertConflict();
        $this->key = (string) Str::uuid();
        $this->save(['capture_code' => $code])->assertConflict();
        $this->save(['capture_code' => 'INVALID'])->assertUnprocessable();
        $this->assertDatabaseCount('photo_captures', 1);
        $this->assertCount(2, Storage::disk('fit-photos')->allFiles());
    }
}

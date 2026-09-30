<?php

namespace Tests\Feature\Admin;

use App\Models\LedAmbienceItem;
use App\Models\LedAmbienceTemplate;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LedAmbienceWebTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaRoot = storage_path('framework/testing/led-ambience-'.uniqid());
        config([
            'led_ambience.media_path' => $this->mediaRoot,
            'pim.media_path' => $this->mediaRoot,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaRoot);
        parent::tearDown();
    }

    public function test_dashboard_shows_five_fixed_global_templates(): void
    {
        $product = Product::factory()->create();
        LedAmbienceItem::create([
            'rfid_tag' => 'E28011606000020468900010',
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $this->get(route('admin.led-ambience.index'))
            ->assertOk()
            ->assertSee('Konfigurasi Konten Global')
            ->assertSee('Idle / Standby')
            ->assertSee('Mountaineering')
            ->assertSee('Lifestyle')
            ->assertSee('Tactical')
            ->assertSee('Riding')
            ->assertSee('RFID LED Aktif')
            ->assertDontSee('Tambah Mapping RFID')
            ->assertDontSee('Tambah Scene');

        $this->assertDatabaseCount('led_ambience_templates', 5);
    }

    public function test_user_can_upload_video_and_audio_to_template_storage(): void
    {
        $template = LedAmbienceTemplate::where('template_key', 'mountaineering')->firstOrFail();

        $response = $this->put(route('admin.led-ambience.templates.update', $template), [
            'description' => 'Visual puncak gunung dengan suara angin.',
            'lighting_color' => '#123ABC',
            'is_active' => 1,
            'video_file' => UploadedFile::fake()->create('mountain.mp4', 120, 'video/mp4'),
            'audio_file' => UploadedFile::fake()->create('wind.mp3', 60, 'audio/mpeg'),
        ]);

        $response->assertRedirect(route('admin.led-ambience.index'));
        $template->refresh();

        $this->assertStringStartsWith('/api/pim-media/led-ambience/videos/mountaineering/', $template->video_url);
        $this->assertStringEndsWith('.mp4', $template->video_url);
        $this->assertStringStartsWith('/api/pim-media/led-ambience/audio/mountaineering/', $template->audio_url);
        $this->assertStringEndsWith('.mp3', $template->audio_url);
        $this->assertSame('#123abc', $template->lighting_color);
        $this->assertFileExists($this->absoluteMediaPath($template->video_url));
        $this->assertFileExists($this->absoluteMediaPath($template->audio_url));
        $this->get($template->video_url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($template->audio_url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_user_can_keep_or_remove_existing_template_media(): void
    {
        $template = LedAmbienceTemplate::where('template_key', 'lifestyle')->firstOrFail();
        $template->update([
            'video_url' => '/api/pim-media/led-ambience/videos/lifestyle/'.str_repeat('a', 64).'.mp4',
            'audio_url' => '/api/pim-media/led-ambience/audio/lifestyle/'.str_repeat('b', 64).'.mp3',
        ]);

        $this->put(route('admin.led-ambience.templates.update', $template), [
            'description' => 'Lifestyle store mood',
            'lighting_color' => '#16a34a',
            'is_active' => 1,
            'remove_video' => 1,
            'remove_audio' => 0,
        ])->assertRedirect(route('admin.led-ambience.index'));

        $template->refresh();
        $this->assertNull($template->video_url);
        $this->assertNotNull($template->audio_url);
    }

    public function test_legacy_mapping_and_free_scene_routes_are_not_available(): void
    {
        $this->get('/admin/led-ambience/rfid-items/create')->assertNotFound();
        $this->get('/admin/led-ambience/scenes/create')->assertNotFound();
    }

    private function absoluteMediaPath(string $url): string
    {
        $relative = str($url)->after('/api/pim-media/')->replace('/', DIRECTORY_SEPARATOR)->toString();

        return $this->mediaRoot.DIRECTORY_SEPARATOR.$relative;
    }
}

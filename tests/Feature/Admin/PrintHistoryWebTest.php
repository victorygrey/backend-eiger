<?php

namespace Tests\Feature\Admin;

use App\Models\FitAndGoDevice;
use App\Models\PhotoCapture;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintHistoryWebTest extends TestCase
{
    use RefreshDatabase;

    protected bool $autoAuthenticate = false;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
    }

    public function test_history_is_a_separate_operational_module_linked_to_fit_and_go_device(): void
    {
        $device = $this->device();
        $capture = PhotoCapture::create([
            'capture_code' => 'FT-20261001-001',
            'fit_and_go_device_id' => $device->id,
            'session_reference' => 'SESSION-001',
            'storage_disk' => 'eiger-media',
            'captured_at' => now()->subMinute(),
            'expires_at' => now()->addWeek(),
        ]);
        PrintJob::create([
            'photo_capture_id' => $capture->id,
            'print_code' => 'PR-20261001-001',
            'printer_name' => 'Photo Printer 01',
            'copies' => 1,
            'status' => 'success',
            'requested_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.print-history.index'))
            ->assertOk()
            ->assertSee('Histori Foto & Print')
            ->assertSee($capture->capture_code)
            ->assertSee($device->name)
            ->assertSee('Berhasil')
            ->assertSee(route('admin.fit-and-go.index'), false);
    }

    public function test_history_can_be_filtered_by_latest_print_status_and_viewed_in_detail(): void
    {
        $device = $this->device();
        $successfulCapture = $this->captureWithJob($device, 'FT-SUCCESS', 'PR-SUCCESS', 'success');
        $failedCapture = $this->captureWithJob($device, 'FT-FAILED', 'PR-FAILED', 'failed', 'Printer kehabisan kertas.');

        $this->actingAs($this->admin)
            ->get(route('admin.print-history.index', ['status' => 'failed']))
            ->assertOk()
            ->assertSee($failedCapture->capture_code)
            ->assertDontSee($successfulCapture->capture_code);

        $this->get(route('admin.print-history.show', $failedCapture))
            ->assertOk()
            ->assertSee($failedCapture->capture_code)
            ->assertSee('PR-FAILED')
            ->assertSee('Printer kehabisan kertas.');
    }

    private function device(): FitAndGoDevice
    {
        return FitAndGoDevice::create([
            'name' => 'AI Fit & Go Lantai 1',
            'device_code' => 'FITGO-TEST-'.FitAndGoDevice::count(),
            'location' => 'Lantai 1',
            'status' => 'online',
            'is_active' => true,
        ]);
    }

    private function captureWithJob(FitAndGoDevice $device, string $captureCode, string $printCode, string $status, ?string $error = null): PhotoCapture
    {
        $capture = PhotoCapture::create([
            'capture_code' => $captureCode,
            'fit_and_go_device_id' => $device->id,
            'storage_disk' => 'eiger-media',
            'captured_at' => now(),
        ]);
        PrintJob::create([
            'photo_capture_id' => $capture->id,
            'print_code' => $printCode,
            'copies' => 1,
            'status' => $status,
            'error_message' => $error,
            'requested_at' => now(),
        ]);

        return $capture;
    }
}

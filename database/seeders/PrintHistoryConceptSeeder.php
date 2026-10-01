<?php

namespace Database\Seeders;

use App\Models\FitAndGoDevice;
use App\Models\PhotoCapture;
use App\Models\PrintJob;
use Illuminate\Database\Seeder;

class PrintHistoryConceptSeeder extends Seeder
{
    public function run(): void
    {
        $floorOne = FitAndGoDevice::firstOrCreate(
            ['device_code' => 'FITGO-LT1-CONCEPT'],
            ['name' => 'AI Fit & Go Lantai 1', 'location' => 'Lantai 1', 'status' => 'online', 'is_active' => true],
        );
        $floorTwo = FitAndGoDevice::firstOrCreate(
            ['device_code' => 'FITGO-LT2-CONCEPT'],
            ['name' => 'AI Fit & Go Lantai 2', 'location' => 'Lantai 2', 'status' => 'online', 'is_active' => true],
        );

        $captures = [
            ['code' => 'FT-20261001-001', 'device' => $floorOne, 'session' => 'SESSION-L1-001', 'minutes' => 7, 'expires' => 7, 'job' => ['code' => 'PR-20261001-001', 'status' => 'success', 'printer' => 'EIGER Photo Printer 01', 'copies' => 1, 'completed' => true]],
            ['code' => 'FT-20261001-002', 'device' => $floorTwo, 'session' => 'SESSION-L2-014', 'minutes' => 18, 'expires' => 7, 'job' => ['code' => 'PR-20261001-002', 'status' => 'printing', 'printer' => 'EIGER Photo Printer 02', 'copies' => 2, 'completed' => false]],
            ['code' => 'FT-20261001-003', 'device' => $floorOne, 'session' => 'SESSION-L1-006', 'minutes' => 42, 'expires' => 7, 'job' => ['code' => 'PR-20261001-003', 'status' => 'failed', 'printer' => 'EIGER Photo Printer 01', 'copies' => 1, 'completed' => true, 'error' => 'Printer kehabisan kertas.']],
            ['code' => 'FT-20260930-021', 'device' => $floorTwo, 'session' => 'SESSION-L2-007', 'minutes' => 1_480, 'expires' => 6, 'job' => ['code' => 'PR-20260930-021', 'status' => 'queued', 'printer' => 'EIGER Photo Printer 02', 'copies' => 1, 'completed' => false]],
        ];

        foreach ($captures as $sample) {
            $capturedAt = now()->subMinutes($sample['minutes']);
            $capture = PhotoCapture::updateOrCreate(
                ['capture_code' => $sample['code']],
                [
                    'fit_and_go_device_id' => $sample['device']->id,
                    'session_reference' => $sample['session'],
                    'storage_disk' => 'eiger-media',
                    'captured_at' => $capturedAt,
                    'expires_at' => now()->addDays($sample['expires']),
                ],
            );

            PrintJob::updateOrCreate(
                ['print_code' => $sample['job']['code']],
                [
                    'photo_capture_id' => $capture->id,
                    'printer_name' => $sample['job']['printer'],
                    'copies' => $sample['job']['copies'],
                    'status' => $sample['job']['status'],
                    'error_message' => $sample['job']['error'] ?? null,
                    'requested_at' => $capturedAt->copy()->addMinute(),
                    'completed_at' => $sample['job']['completed'] ? $capturedAt->copy()->addMinutes(2) : null,
                ],
            );
        }
    }
}

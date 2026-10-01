<?php

namespace Tests\Feature\Api;

use App\Services\DeviceImageWebpService;
use Tests\TestCase;

class DeviceImageWebpTest extends TestCase
{
    public function test_device_media_never_downloads_or_rewrites_a_remote_image(): void
    {
        $remoteImage = 'https://storage.eigeradventure.com/products/photo.jpg';

        $this->assertSame($remoteImage, app(DeviceImageWebpService::class)->url($remoteImage));
        $this->assertNull(app(DeviceImageWebpService::class)->url(null));
    }
}

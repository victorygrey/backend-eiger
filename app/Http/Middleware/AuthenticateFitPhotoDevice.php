<?php

namespace App\Http\Middleware;

use App\Models\FitAndGoDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFitPhotoDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = FitAndGoDevice::where('device_code', $request->route('deviceCode'))->where('is_active', true)->first();
        $token = $request->bearerToken();
        // Unlike the public catalog, photo access always requires a paired device.
        abort_unless($device && $token && $device->device_token_hash && Hash::check($token, $device->device_token_hash), 401, 'Invalid kiosk credential.');
        $request->attributes->set('photo_device', $device);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}

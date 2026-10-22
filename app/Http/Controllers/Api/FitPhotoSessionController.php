<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FitAndGoDevice;
use App\Models\PhotoCapture;
use App\Services\FitPhotoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FitPhotoSessionController extends Controller
{
    public function store(Request $request, FitPhotoStorage $storage): JsonResponse
    {
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'consent' => ['required', Rule::in(['accepted'])],
            'image' => ['required', 'file', 'mimetypes:image/png,image/jpeg,image/webp', 'max:62500', 'dimensions:max_width=16000,max_height=16000'],
            'gender' => ['required', Rule::in(['men', 'women'])],
            'activity' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/'],
            'background_id' => ['nullable', 'string', 'max:100'],
            'products' => ['required', 'json', 'max:16000'],
        ]);
        $products = json_decode($data['products'], true);
        $products = Validator::make(['products' => $products], [
            'products' => ['required', 'array', 'min:1', 'max:5'],
            'products.*' => ['required', 'array:product_id,product_sku,color,category,catalog_item_id'],
            'products.*.product_id' => ['required', 'integer', 'min:1'],
            'products.*.product_sku' => ['required', 'string', 'max:100'],
            'products.*.color' => ['nullable', 'string', 'max:100'],
            'products.*.category' => ['required', Rule::in(['hat', 'upper', 'lower', 'shoes', 'bag']), 'distinct'],
            'products.*.catalog_item_id' => ['required', 'integer', 'min:1'],
        ])->validate()['products'];
        // Store snapshots: static color-reference IDs are not product foreign keys.
        $products = array_map(fn ($p) => [
            'product_id' => (int) $p['product_id'],
            'product_sku' => $p['product_sku'],
            'color' => $p['color'] ?? null,
            'category' => $p['category'],
            'catalog_item_id' => (int) $p['catalog_item_id'],
        ], array_values($products));
        usort($products, fn ($a, $b) => strcmp($a['category'], $b['category']));
        $file = $request->file('image');
        $dimensions = getimagesize($file->getRealPath());
        abort_if($dimensions[0] * $dimensions[1] > 40000000, 422, 'Image exceeds 40 megapixels.');
        $metadata = [
            'gender' => $data['gender'],
            'activity' => $data['activity'],
            'background_id' => $data['background_id'] ?? null,
            'products' => $products,
        ];
        $digest = hash('sha256', json_encode($metadata, JSON_THROW_ON_ERROR).hash_file('sha256', $file->getRealPath()));
        $device = $request->attributes->get('photo_device');
        $paths = [];
        $created = false;
        try {
            $capture = DB::transaction(function () use ($device, $data, $digest, $metadata, $file, $storage, &$paths, &$created) {
                // A real write also serializes SQLite transactions; SELECT FOR UPDATE alone does not.
                FitAndGoDevice::whereKey($device->id)->update(['updated_at' => now()]);
                $existing = PhotoCapture::where('fit_and_go_device_id', $device->id)->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    abort_if(! $existing->expires_at->isFuture() || $existing->purged_at, 410, 'Session expired; start a new fitting.');
                    abort_unless(hash_equals($existing->request_hash, $digest), 409, 'Idempotency key belongs to a different result.');

                    return $existing;
                }
                do {
                    $code = strtoupper(bin2hex(random_bytes(5)));
                } while (PhotoCapture::where('capture_code', $code)->exists());
                $directory = now('UTC')->format('Y/m/d').'/'.Str::uuid();
                $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$file->getMimeType()];
                $paths[] = $path = $directory.'/final.'.$extension;
                Storage::disk('fit-photos')->putFileAs($directory, $file, 'final.'.$extension);
                $paths[] = $thumbnailPath = $directory.'/preview.jpg';
                $thumbnailPath = $storage->thumbnail($file, $thumbnailPath);
                $created = true;

                return PhotoCapture::create([
                    ...$metadata,
                    'capture_code' => $code,
                    'fit_and_go_device_id' => $device->id,
                    'idempotency_key' => $data['idempotency_key'],
                    'session_reference' => $data['idempotency_key'],
                    'request_hash' => $digest,
                    'photo_path' => $path,
                    'thumbnail_path' => $thumbnailPath,
                    'storage_disk' => 'fit-photos',
                    'image_mime' => $file->getMimeType(),
                    'image_size' => $file->getSize(),
                    'captured_at' => now('UTC'),
                    'consented_at' => now('UTC'),
                    'expires_at' => now(config('fit_photos.timezone'))->endOfDay()->utc(),
                ]);
            });
        } catch (Throwable $error) {
            foreach ($paths as $path) {
                Storage::disk('fit-photos')->delete($path);
            }
            throw $error;
        }

        return response()->json(['status' => 'success', 'data' => $this->receipt($capture)], $created ? 201 : 200);
    }

    public function show(Request $request, string $deviceCode, string $code): JsonResponse
    {
        $capture = $this->capture($request, $code);

        return response()->json(['status' => 'success', 'data' => $this->receipt($capture)]);
    }

    public function image(Request $request, string $deviceCode, string $code, FitPhotoStorage $storage): StreamedResponse
    {
        return $storage->response($this->capture($request, $code));
    }

    private function capture(Request $request, string $code): PhotoCapture
    {
        $capture = PhotoCapture::where('fit_and_go_device_id', $request->attributes->get('photo_device')->id)
            ->where('capture_code', $code)->firstOrFail();
        abort_unless($capture->hasAvailablePhoto(), 410, 'Photo expired.');

        return $capture;
    }

    private function receipt(PhotoCapture $capture): array
    {
        return [
            'id' => $capture->id,
            'code' => $capture->capture_code,
            'device_code' => $capture->device->device_code,
            'products' => $capture->products,
            'expires_at' => $capture->expires_at->utc()->toISOString(),
        ];
    }
}

<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PimFormData
{
    public function apply(array $data, Request $request): array
    {
        if (!$request->filled('pim_payload_json')) return $data;
        try {
            $input = [
                'product' => json_decode($request->input('pim_payload_json'), true, 64, JSON_THROW_ON_ERROR),
                'image' => json_decode($request->input('pim_image_payload_json', ''), true, 64, JSON_THROW_ON_ERROR),
            ];
        } catch (\JsonException $e) {
            throw ValidationException::withMessages(['pim_payload_json' => 'Payload PIM harus berupa JSON yang valid.']);
        }
        $service = app(PimPayload::class);
        $payload = $service->validate($input);
        if (!collect($payload['product']['variant'])->contains('sku', $data['sku'] ?? '')) {
            throw ValidationException::withMessages(['sku' => 'Pilih SKU yang terdapat dalam payload PIM.']);
        }
        return array_merge($data, [
            'pim_payload' => $payload['product'],
            'pim_image_payload' => $payload['image'],
        ], $service->mediaValues($payload['product'], $payload['image'], $data['sku']));
    }
}

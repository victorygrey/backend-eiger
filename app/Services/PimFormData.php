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
        $genericSku = (string) ($payload['product']['generic'] ?? '');
        $currentSku = (string) ($data['sku'] ?? '');
        $variantSkus = collect($payload['product']['variant'] ?? [])->pluck('sku')->map(fn($s) => (string)$s)->all();

        $skuMatches = $currentSku === $genericSku
            || in_array($currentSku, $variantSkus, true)
            || (!empty($genericSku) && str_starts_with($currentSku, $genericSku));

        if (!$skuMatches) {
            throw ValidationException::withMessages(['sku' => 'SKU harus sesuai dengan kode generic (' . $genericSku . ') atau salah satu SKU varian dalam payload PIM.']);
        }
        return array_merge($data, [
            'pim_payload' => $payload['product'],
            'pim_image_payload' => $payload['image'],
        ], $service->mediaValues($payload['product'], $payload['image'], $data['sku']));
    }
}

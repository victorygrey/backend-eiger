<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PimProductLookup
{
    public function get(string $code): array
    {
        $base = rtrim(config('pim.url'), '/').'/api/articles/'.rawurlencode($code);
        $product = Http::acceptJson()->connectTimeout(3)->timeout(config('pim.timeout'))->get($base.'/product-payload');
        $images = Http::acceptJson()->connectTimeout(3)->timeout(config('pim.timeout'))->get($base.'/image-payload');
        foreach ([$product, $images] as $response) {
            if ($response->status() === 404) {
                $message = is_array($response->json())
                    ? 'Kode produk tidak ditemukan di PIM.'
                    : 'Endpoint payload belum tersedia di server PIM. Perbarui deployment simulator TrueNAS.';
                throw ValidationException::withMessages(['pim_code' => $message]);
            }
        }
        $product->throw(); $images->throw();
        if (!is_array($product->json('variant')) || !is_array($images->json('variant'))) throw ValidationException::withMessages(['pim_code' => 'Respons PIM tidak sesuai kontrak payload produk.']);
        return app(PimPayload::class)->validate(['product' => $product->json(), 'image' => $images->json()]);
    }
}

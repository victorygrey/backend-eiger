<?php

namespace App\Services;

class CareProductLookup
{
    public function __construct(private readonly CareOmniClient $care)
    {
    }

    /**
     * Read one article and all of its sellable variants from CARE.
     *
     * @return array{found: bool, price: float, stock: int, variants: array<int, array<string, mixed>>}
     */
    public function get(string $articleSku, array $variantSkus = []): array
    {
        return $this->care->article($articleSku, $variantSkus);
    }
}

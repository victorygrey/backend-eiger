<?php

namespace Tests\Concerns;

use App\Models\Product;

trait ConfiguresFitAndGoProducts
{
    private function configureFitProduct(Product $product): void
    {
        $product->update([
            'ai_fit_and_go_active' => true,
            'image' => $product->image ?: '/api/pim-media/test-cover.jpg',
        ]);
        $product->fitAndGoConfiguration()->create([
            'image' => $product->image,
            'description' => 'Deskripsi khusus AI Fit & Go untuk '.$product->name,
        ]);
    }
}

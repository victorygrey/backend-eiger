<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'product_id' => $this->product_id,
            'sku'        => $this->sku,
            'name'       => $this->name,
            'color'      => $this->color,
            'size'       => $this->size,
            'ecmsku'     => $this->ecmsku,
            'moq'        => $this->moq,
            'custom_attributes' => $this->custom_attributes ?? [],
            'price'      => $this->price,
            'stock'      => $this->stock,
            'image'      => str_starts_with($this->image ?? '', '/api/pim-media/') ? url($this->image) : $this->image,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Support\PimMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'sku'             => $this->sku,
            'name'            => $this->name,
            'price'           => $this->price,
            'stock'           => $this->stock,
            'zone_id'         => $this->zone_id,
            'zone'            => new ZoneResource($this->whenLoaded('zone')),
            'image'           => PimMediaUrl::toPublicUrl($this->image),
            'pim_media'       => collect($this->pim_media ?? [])->map(fn ($media) => array_merge($media, [
                'url' => PimMediaUrl::toPublicUrl($media['url'] ?? null),
            ]))->all(),
            'material'        => $this->material,
            'category'        => $this->category,
            'technologies'    => $this->technologies,
            'activities'      => $this->activities,
            'performances'    => $this->performances,
            'performance'     => $this->performances,
            'specifications'  => $this->specifications,
            'custom_attributes' => $this->custom_attributes_list,
            'data_sources'     => [
                'product' => 'PIM',
                'commercial' => 'CARE',
                'pim_synced_at' => $this->pim_synced_at?->toIso8601String(),
                'care_synced_at' => $this->care_synced_at?->toIso8601String(),
            ],
            'atom_category'   => $this->whenLoaded('atomCategory'),
            'atom_sub_category' => $this->whenLoaded('atomSubCategory'),
            'pim_payload'     => $this->pim_payload,
            'pim_image_payload' => $this->pim_image_payload,
            'description'     => $this->description,
            'is_featured'     => $this->is_featured,
            'is_discontinued' => $this->is_discontinued,
            'variants_count'  => $this->whenCounted('variants', $this->variants_count, fn () => $this->variants()->count()),
            'variants'        => ProductVariantResource::collection($this->whenLoaded('variants')),
            'rfid_tag'        => new RfidTagResource($this->whenLoaded('rfidTag')),
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}

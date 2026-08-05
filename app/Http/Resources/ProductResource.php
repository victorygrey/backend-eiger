<?php

namespace App\Http\Resources;

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
            'image'           => $this->image,
            'material'        => $this->material,
            'description'     => $this->description,
            'is_featured'     => $this->is_featured,
            'is_discontinued' => $this->is_discontinued,
            'rfid_tag'        => new RfidTagResource($this->whenLoaded('rfidTag')),
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}

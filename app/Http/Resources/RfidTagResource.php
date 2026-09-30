<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfidTagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'name' => $this->name,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'led_ambience_active' => $this->whenLoaded(
                'ledAmbienceItem',
                fn (): bool => (bool) $this->ledAmbienceItem?->is_active,
            ),
            'table_expedition_active' => $this->whenLoaded(
                'tableExpeditionItem',
                fn (): bool => (bool) $this->tableExpeditionItem?->is_active,
            ),
            'last_scanned_at' => $this->last_scanned_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

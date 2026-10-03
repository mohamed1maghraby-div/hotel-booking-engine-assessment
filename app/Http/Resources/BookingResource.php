<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'booking_number' => $this->booking_number,
            'status' => $this->status,
            
            'hotel_id' => $this->hotel_id,
            
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            
            'rooms_count' => $this->rooms_count,
            
            'total_amount' => $this->total_amount,
            
            'currency' => $this->currency,
            
            'items' => BookingItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

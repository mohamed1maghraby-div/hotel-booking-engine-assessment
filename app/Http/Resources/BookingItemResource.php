<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemResource extends JsonResource
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
            // 'booking_id' => $this->booking_id,
            'room_type_id' => $this->room_type_id,
            'quantity' => $this->quantity,
            'price_per_night' => $this->price_per_night,
            'total_price' => $this->total_price,
        ];
    }
}

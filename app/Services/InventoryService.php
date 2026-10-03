<?php

namespace App\Services;

use App\Models\Inventory;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    
    public function reserve(
        int $roomTypeId,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $quantity,
    ): void {
        $inventories = Inventory::query()
            ->where('room_type_id', $roomTypeId)
            ->whereDate('date', '>=', $checkIn->toDateString())
            ->whereDate('date', '<', $checkOut->toDateString())
            ->orderBy('date')
            ->lockForUpdate()
            ->get();

        $expectedNights = (int) $checkIn->diffInDays($checkOut);

        if ($inventories->count() !== $expectedNights) {
            throw ValidationException::withMessages([
                'date' => 'Inventory is not available for one or more requested nights.',
            ]);
        }

        foreach ($inventories as $inventory) {

            if(
                $inventory->booked_rooms + $quantity > $inventory->total_rooms
            ) {
                throw ValidationException::withMessages([
                    'rooms' => sprintf(
                        'Insufficient available for %s.',
                        $inventory->date->toDateString()
                    )
                ]);
            }
        }

        foreach ($inventories as $inventory) {
            $inventory->increment('booked_rooms', $quantity);
        }
    }
    
    public function release(
        int $roomTypeId,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $quantity,
    ): void {
        $inventories = Inventory::query()
            ->where('room_type_id', $roomTypeId)
            ->whereDate('date', '>=', $checkIn->toDateString())
            ->whereDate('date', '<', $checkOut->toDateString())
            ->orderBy('date')
            ->lockForUpdate()
            ->get();

        $expectedNights = (int) $checkIn->diffInDays($checkOut);

        if ($inventories->count() !== $expectedNights) {
            throw ValidationException::withMessages([
                'date' => 'Inventory is not available for one or more requested nights.',
            ]);
        }

        foreach ($inventories as $inventory) {
            if($inventory->booked_rooms < $quantity) {
                throw ValidationException::withMessages([
                    'rooms' => sprintf(
                        'Cannot release more rooms than booked for %s.',
                        $quantity,
                        $inventory->date->toDateString(),
                        $inventory->booked_rooms
                    )
                ]);
            }
        }

        foreach ($inventories as $inventory) {
            $inventory->decrement('booked_rooms', $quantity);
        }
    }
}

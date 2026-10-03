<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Hotel;
use App\Models\IdempotencyKey;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly BookingNumberGenerator $bookingNumberGenerator
    ) {}

    public function create(
        int $userId,
        array $data,
        string $idempotencyKey
    ): Booking {
        $requestHash = hash(
            'sha256',
            json_encode($data, JSON_THROW_ON_ERROR)
        );

        return DB::transaction(function () use (
            $userId,
            $data,
            $idempotencyKey,
            $requestHash
        ) {
            $existingKey = IdempotencyKey::query()
                ->where('user_id', $userId)
                ->where('key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingKey) {
                if (! hash_equals(
                    $existingKey->request_hash,
                    $requestHash
                )) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This key was already used with a different request.'
                    ]);
                }

                if ($existingKey->booking) {
                    return $existingKey->booking->load('items');
                }

                throw ValidationException::withMessages([
                    'idempotency_key' => 'A request with this key is already being processed.'
                ]);
            }

            $idempotancy = IdempotencyKey::create([
                'user_id' => $userId,
                'key' => $idempotencyKey,
                'request_hash' => $requestHash
            ]);

            $hotel = Hotel::query()
                ->whereKey($data['hotel_id'])
                ->where('is_active', true)
                ->firstOrFail();

            $roomType = RoomType::query()
                ->whereKey($data['room_type_id'])
                ->where('hotel_id', $hotel->id)
                ->where('is_active', true)
                ->firstOrFail();

            $checkIn = CarbonImmutable::parse($data['check_in']);
            $checkOut = CarbonImmutable::parse($data['check_out']);

            $quantity = (int) $data['quantity'];

            $this->inventoryService->reserve(
                $roomType->id,
                $checkIn,
                $checkOut,
                $quantity
            );

            $nights = $checkIn->diffInDays($checkOut);

            $totalAmount = (float) $roomType->price_per_night * $quantity * $nights;

            $booking = Booking::create([
                'booking_number' => $this->bookingNumberGenerator->generate(),
                'user_id' => $userId,
                'hotel_id' => $hotel->id,
                'status' => Booking::STATUS_CONFIRMED,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'rooms_count' => $quantity,
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'currency' => 'EGP',
                'confirmed_at' => now(),
            ]);

            BookingItem::create([
                'booking_id' => $booking->id,
                'room_type_id' => $roomType->id,
                'quantity' => $quantity,
                'price_per_night' => $roomType->price_per_night,
                'total_price' => number_format($totalAmount, 2, '.', '')
            ]);

            $idempotancy->update([
                'booking_id' => $booking->id
            ]);

            return $booking->load('items');
        }, attempts: 3);
    }

    public function cancel(Booking $booking, User $user): Booking
    {
        return DB::transaction(function () use ($booking, $user) {
            
            $booking = Booking::query()
                ->whereKey($booking->id)
                ->whereUserId($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if( $booking->status === Booking::STATUS_CANCELLED ) {
                return $booking;
            }

            if($booking->status !== Booking::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'booking' => 'Only confirmed bookings can be cancelled.'
                ]);
            }

            $bookingItems = $booking->items()->orderBy('id')->get();

            foreach ($bookingItems as $item) {
                $this->inventoryService->release(
                    roomTypeId: $item->room_type_id,
                    checkIn: CarbonImmutable::parse($booking->check_in),
                    checkOut: CarbonImmutable::parse($booking->check_out),
                    quantity: $item->quantity
                );
            }

            $booking->update([
                'status' => Booking::STATUS_CANCELLED,
                'cancelled_at' => now()
            ]);

            return $booking->fresh();
        });
    }
}

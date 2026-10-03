<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService
    )
    {
        
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (
            !is_string($idempotencyKey) ||
            strlen($idempotencyKey) < 16 ||
            strlen($idempotencyKey) > 100
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The Idempotency-Key header is required and must be between 16 and 100 characters.'
            ]);
        }

        try {
            $booking = $this->bookingService->create(
                userId: (int) $request->user()->id,
                data: $request->validated(),
                idempotencyKey: $idempotencyKey
            );

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully.',
                'data' => new BookingResource($booking)
            ], 201);

        } catch (Throwable $exception) {
            Log::error('Booking creation failed', [
                'user_id' => $request->user()->id,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function cancel(Request $request, Booking $booking)
    {
        $booking = $this->bookingService->cancel(
            $booking,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled successfully.',
            'data' => new BookingResource($booking)
        ]);
    }
}

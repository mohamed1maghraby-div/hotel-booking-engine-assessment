<?php

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Inventory;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;

uses(DatabaseMigrations::class);

it('prevents overbooking under concurrent booking requests', function () {
    /*
    |--------------------------------------------------------------------------
    | Arrange
    |--------------------------------------------------------------------------
    */

    $this->seed(\Database\Seeders\AssessmentSeeder::class);

    $hotel = Hotel::query()
        ->where('code', 'ASSESSMENT-TEST')
        ->firstOrFail();

    $roomType = RoomType::query()
        ->where('hotel_id', $hotel->id)
        ->where('code', 'ASSESSMENT-ROOM')
        ->firstOrFail();

    $user = User::factory()->create();

    $token = $user->createToken('concurrency-test')->plainTextToken;

    $checkIn = '2026-10-10';
    $checkOut = '2026-10-13';
    $quantity = 2;

    /*
    |--------------------------------------------------------------------------
    | Start all workers at approximately the same time
    |--------------------------------------------------------------------------
    */

    $startAt = microtime(true) + 2;

    $processes = [];

    for ($i = 1; $i <= 5; $i++) {
        $processes[$i] = new Process([
            PHP_BINARY,
            base_path('tests/Support/BookingConcurrencyWorker.php'),

            '--url=http://127.0.0.1:8001/api/v1/bookings',
            '--token=' . $token,
            '--hotel_id=' . $hotel->id,
            '--room_type_id=' . $roomType->id,
            '--check_in=' . $checkIn,
            '--check_out=' . $checkOut,
            '--quantity=' . $quantity,
            '--idempotency_key=concurrency-test-' . $i,
            '--start_at=' . $startAt,
        ], base_path(), [
            'APP_ENV' => 'testing',
        ]);

        $processes[$i]->start();
    }

    /*
    |--------------------------------------------------------------------------
    | Wait for all workers
    |--------------------------------------------------------------------------
    */

    $results = [];

    foreach ($processes as $i => $process) {
        $process->wait();

        if (! $process->isSuccessful()) {
            dump([
                'worker' => $i,
                'exit_code' => $process->getExitCode(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ]);
        }

        expect($process->isSuccessful())
            ->toBeTrue(
                "Worker {$i} failed:\n" . $process->getErrorOutput()
            );

        $output = trim($process->getOutput());

        expect($output)->not->toBeEmpty();

        $result = json_decode($output, true);

        expect($result)->toBeArray();

        $results[$i] = $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Assert HTTP results
    |--------------------------------------------------------------------------
    */

    $statuses = array_column($results, 'status');

    $successfulRequests = count(
        array_filter(
            $statuses,
            fn (int $status) => $status === 201
        )
    );

    $failedRequests = count(
        array_filter(
            $statuses,
            fn (int $status) => $status === 422
        )
    );

    expect($successfulRequests)->toBe(2);
    expect($failedRequests)->toBe(3);

    /*
    |--------------------------------------------------------------------------
    | Assert final inventory
    |--------------------------------------------------------------------------
    */

    $inventories = Inventory::query()
        ->where('room_type_id', $roomType->id)
        ->whereBetween('date', [$checkIn, '2026-10-12'])
        ->orderBy('date')
        ->get();

    expect($inventories)->toHaveCount(3);

    expect($inventories->pluck('booked_rooms')->all())
        ->toBe([4, 4, 4]);

    /*
    |--------------------------------------------------------------------------
    | Assert successful bookings
    |--------------------------------------------------------------------------
    */

    $successfulBookings = Booking::query()
        ->where('hotel_id', $hotel->id)
        ->where('check_in', $checkIn)
        ->where('check_out', $checkOut)
        ->where('rooms_count', $quantity)
        ->where('status', 'CONFIRMED')
        ->count();

    expect($successfulBookings)->toBe(2);
});

it('prevents a user from cancelling another user booking', function () {
    $this->seed(\Database\Seeders\AssessmentSeeder::class);

    $hotel = Hotel::where('code', 'ASSESSMENT-TEST')->firstOrFail();
    $roomType = RoomType::where('code', 'ASSESSMENT-ROOM')->firstOrFail();

    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($owner, 'sanctum');

    $response = $this->postJson('/api/v1/bookings', [
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'check_in' => '2026-10-10',
        'check_out' => '2026-10-13',
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'authorization-test',
    ]);

    $response->assertCreated();

    $bookingId = $response->json('data.id');

    $this->actingAs($otherUser, 'sanctum');

    $this->postJson("/api/v1/bookings/{$bookingId}/cancel")
        ->assertNotFound();
});


it('validates booking dates and quantity', function () {
    $this->seed(\Database\Seeders\AssessmentSeeder::class);

    $user = User::factory()->create();
    $hotel = Hotel::where('code', 'ASSESSMENT-TEST')->firstOrFail();
    $roomType = RoomType::where('code', 'ASSESSMENT-ROOM')->firstOrFail();

    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/v1/bookings', [
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'check_in' => '2026-10-13',
        'check_out' => '2026-10-10',
        'quantity' => 0,
    ], [
        'Idempotency-Key' => 'validation-test',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'check_out',
            'quantity',
        ]);
});


it('does not create duplicate booking with the same idempotency key', function () {
    $this->seed(\Database\Seeders\AssessmentSeeder::class);

    $user = User::factory()->create();

    $hotel = Hotel::where('code', 'ASSESSMENT-TEST')->firstOrFail();
    $roomType = RoomType::where('code', 'ASSESSMENT-ROOM')->firstOrFail();

    $this->actingAs($user, 'sanctum');

    $payload = [
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'check_in' => '2026-10-10',
        'check_out' => '2026-10-13',
        'quantity' => 1,
    ];

    $headers = [
        'Idempotency-Key' => 'duplicate-booking-test',
    ];

    $first = $this->postJson('/api/v1/bookings', $payload, $headers);

    $first->assertCreated();

    $second = $this->postJson('/api/v1/bookings', $payload, $headers);

    $second->assertSuccessful();

    expect(Booking::count())->toBe(1);

    expect(
        Inventory::where('room_type_id', $roomType->id)
            ->whereBetween('date', ['2026-10-10', '2026-10-12'])
            ->pluck('booked_rooms')
            ->all()
    )->toBe([1, 1, 1]);
});
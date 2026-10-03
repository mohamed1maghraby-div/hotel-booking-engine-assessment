<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Inventory;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::updateOrCreate(
            ['code' => 'ASSESSMENT-TEST'],
            [
                'name' => 'Assessment Test Hotel',
                'city' => 'Cairo',
                'country' => 'Egypt',
                'is_active' => true,
            ]
        );

        $roomType = RoomType::updateOrCreate(
            [
                'hotel_id' => $hotel->id,
                'code' => 'ASSESSMENT-ROOM',
            ],
            [
                'name' => 'Assessment Test Room',
                'max_guests' => 2,
                'price_per_night' => 1500,
                'is_active' => true,
            ]
        );

        $checkIn = '2026-10-10';
        $checkOut = '2026-10-13';

        foreach ([
            '2026-10-10',
            '2026-10-11',
            '2026-10-12',
        ] as $date) {
            Inventory::updateOrCreate(
                [
                    'room_type_id' => $roomType->id,
                    'date' => $date,
                ],
                [
                    'total_rooms' => 5,
                    'booked_rooms' => 0,
                ]
            );
        }

        $this->command->info("Hotel ID: {$hotel->id}");
        $this->command->info("Room Type ID: {$roomType->id}");
        $this->command->info("Check-in: {$checkIn}");
        $this->command->info("Check-out: {$checkOut}");
    }
}
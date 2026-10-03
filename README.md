# Hotel Booking Engine

A Laravel-based hotel booking API focused on reliable room inventory management, concurrency-safe reservations, idempotent booking requests, and safe cancellation.

## Features

* Hotel and room type management
* Date-based room inventory
* Authenticated booking API using Laravel Sanctum
* Unique booking numbers
* Multi-night booking support
* Transactional inventory reservation
* Concurrency-safe inventory locking
* Protection against room overbooking
* `Idempotency-Key` support
* Safe repeated requests
* Safe booking cancellation
* Inventory restoration on cancellation
* User-level booking authorization
* Request validation
* Automated feature tests
* Process-level concurrency test

---

## Requirements

* PHP 8.3+
* Composer
* MySQL 8+
* Laravel
* Laragon / XAMPP / equivalent local PHP environment

---

## Installation

Clone the project and install dependencies:

```bash
composer install
```

Create the environment file:

```bash
copy .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure the database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hotel_booking_engine
DB_USERNAME=root
DB_PASSWORD=
```

Run migrations:

```bash
php artisan migrate
```

Seed the assessment data:

```bash
php artisan db:seed --class=AssessmentSeeder
```

Start the application:

```bash
php artisan serve
```

The API will be available at:

```text
http://127.0.0.1:8000
```

---

# Authentication

The API uses Laravel Sanctum.

Create an authenticated user and token, then send the token with every protected request:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
Content-Type: application/json
```

---

# Booking API

## Create Booking

```http
POST /api/v1/bookings
```

### Headers

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
Content-Type: application/json
Idempotency-Key: unique-request-key
```

`Idempotency-Key` is required to prevent accidental duplicate bookings when the same request is retried.

### Request

```json
{
    "hotel_id": 1,
    "room_type_id": 1,
    "check_in": "2026-10-04",
    "check_out": "2026-10-07",
    "quantity": 2
}
```

### Successful Response

```json
{
    "success": true,
    "message": "Booking created successfully.",
    "data": {
        "id": 1,
        "booking_number": "BK-...",
        "status": "CONFIRMED",
        "hotel_id": 1,
        "check_in": "2026-10-04T00:00:00.000000Z",
        "check_out": "2026-10-07T00:00:00.000000Z",
        "rooms_count": 2,
        "total_amount": "9000.00",
        "currency": "EGP"
    }
}
```

The total amount is calculated as:

```text
price_per_night × quantity × number_of_nights
```

---

# Idempotency

The booking endpoint requires an `Idempotency-Key`.

For example:

```http
Idempotency-Key: order-12345
```

## Same key + same request

The original booking is returned/replayed and a duplicate booking is not created.

## Same key + different request

The API rejects the request:

```json
{
    "message": "This key was already used with a different request.",
    "errors": {
        "idempotency_key": [
            "This key was already used with a different request."
        ]
    }
}
```

The idempotency key is scoped to the authenticated user.

---

# Cancellation API

## Cancel Booking

```http
POST /api/v1/bookings/{booking}/cancel
```

### Headers

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

Cancellation:

1. Locks the booking row.
2. Verifies that the booking belongs to the authenticated user.
3. Verifies the booking status.
4. Restores the reserved inventory.
5. Changes the booking status to `CANCELLED`.
6. Records `cancelled_at`.

Repeated cancellation does not restore the inventory more than once.

---

# Inventory Management

Inventory is stored per room type and per night.

For a booking from:

```text
2026-10-10
```

to:

```text
2026-10-13
```

three inventory records are required:

```text
2026-10-10
2026-10-11
2026-10-12
```

The checkout date is excluded from the number of nights.

Before reservation, all required inventory rows are locked using:

```php
->lockForUpdate()
```

The service then verifies availability before incrementing `booked_rooms`.

This prevents concurrent requests from reserving the same remaining rooms.

---

# Concurrency Protection

The application uses database transactions and row-level locking to protect inventory from concurrent bookings.

The assessment includes a process-level concurrency test that starts five independent PHP processes attempting to reserve:

```text
5 available rooms
2 rooms per request
```

Expected result:

```text
2 successful bookings
3 rejected requests
```

The successful requests reserve:

```text
2 × 2 = 4 rooms
```

leaving one room unavailable for another request requiring two rooms.

The test also verifies that all requested nights have the correct `booked_rooms` value.

---

# Database Design

Main tables:

```text
hotels
room_types
inventories
bookings
booking_items
idempotency_keys
users
personal_access_tokens
```

Important constraints include:

* Unique hotel code
* Unique room type code per hotel
* Unique inventory record per room type/date
* Unique booking number
* Foreign key constraints
* Cascading booking item deletion
* User-scoped idempotency keys

---

# Testing

The project uses Pest.

Run the complete test suite:

```bash
php artisan test
```

Current test suite covers:

* Concurrent booking protection
* Overbooking prevention
* User booking authorization
* Booking validation
* Idempotent booking requests
* Laravel application bootstrapping

Current result:

```text
Tests:    6 passed (32 assertions)
```

---

# Concurrency Test Environment

The process-level concurrency test uses a dedicated MySQL testing database.

Create the database:

```sql
CREATE DATABASE hotel_booking_engine_assessment_test;
```

Configure `.env.testing`:

```env
APP_ENV=testing
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hotel_booking_engine_assessment_test
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

Generate the testing application key if required:

```bash
php artisan key:generate --env=testing
```

The concurrency test requires a running Laravel testing server.

Example:

```bash
php artisan serve --env=testing --port=8001
```

The test workers then send independent HTTP requests to the testing application.

---

# Assessment Seeder

The project contains a single assessment seeder:

```text
database/seeders/AssessmentSeeder.php
```

It creates:

* Assessment test hotel
* Assessment test room type
* Three nights of inventory
* Five total rooms per night

Run it with:

```bash
php artisan db:seed --class=AssessmentSeeder
```

---

# API Error Handling

Common responses include:

### Validation Error

```text
422 Unprocessable Entity
```

### Insufficient Inventory

```text
422 Unprocessable Entity
```

Example:

```json
{
    "message": "Insufficient available for 2026-10-10.",
    "errors": {
        "rooms": [
            "Insufficient available for 2026-10-10."
        ]
    }
}
```

### Unauthenticated

```text
401 Unauthorized
```

### Unauthorized Booking Access

A user cannot cancel another user's booking.

---

# Project Structure

Relevant application components:

```text
app/
├── Http/
│   └── Controllers/
├── Models/
└── Services/
    └── InventoryService.php
    └── BookingService.php

database/
├── migrations/
└── seeders/
    └── AssessmentSeeder.php

tests/
├── Feature/
│   ├── AssessmentTest.php
│   └── ExampleTest.php
├── Support/
│   └── BookingConcurrencyWorker.php
└── Unit/
    └── ExampleTest.php
```

---

# Design Decisions

### Database Transactions

Booking creation and inventory reservation are performed inside a database transaction so that a failure during booking creation does not leave partially reserved inventory.

### Row-Level Locking

Inventory rows are locked with `lockForUpdate()` before availability is checked and updated.

### Idempotency

Idempotency records associate a request key with a request hash and resulting booking. This prevents duplicate bookings caused by client retries.

### Unique Booking Numbers

Each booking receives a unique booking number backed by a database unique constraint.

### Cancellation Safety

Cancellation locks the booking row before checking its current status. This prevents two concurrent cancellation requests from restoring the same inventory twice.

---

# Running the Application

```bash
composer install
php artisan migrate
php artisan db:seed --class=AssessmentSeeder
php artisan serve
```

Then authenticate and use:

```text
POST /api/v1/bookings
POST /api/v1/bookings/{booking}/cancel
```

---

# License

This project is developed as part of a Laravel hotel booking engine technical assessment.

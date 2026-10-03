<?php

namespace App\Services;

use Illuminate\Support\Str;

class BookingNumberGenerator
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function generate(): string
    {
        return 'BK-' . Str::upper((string) Str::uuid());
    }
}

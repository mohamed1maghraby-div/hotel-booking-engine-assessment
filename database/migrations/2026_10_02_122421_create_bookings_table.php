<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->string('booking_number', 40)->unique();

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('hotel_id')->constrained('hotels')->restrictOnDelete();

            $table->string('status', 20)->default('PENDING');

            $table->date('check_in');
            $table->date('check_out');

            $table->unsignedSmallInteger('rooms_count');

            $table->decimal('total_amount', 12, 2);

            $table->string('currency', 3)->default('EGP');

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['hotel_id', 'check_in', 'check_out']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

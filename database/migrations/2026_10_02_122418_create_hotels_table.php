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
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('code', 30)->unique();

            $table->string('city', 100);
            $table->string('country', 100)->default('Egypt');

            $table->boolean('is_active')->default(true);
            
            $table->timestamps();

            $table->index(['is_active', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};

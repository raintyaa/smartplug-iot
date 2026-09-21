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
        Schema::create('power_readings', function (Blueprint $table) {
            $table->id();
            $table->decimal('voltage', 6, 2)->default(0); // Volt (V)
            $table->decimal('current', 6, 3)->default(0); // Ampere (A)
            $table->decimal('power', 8, 2)->default(0); // Watt (W)
            $table->decimal('energy', 10, 4)->default(0); // Total kWh
            $table->decimal('frequency', 5, 1)->nullable()->default(50.0); // Hz
            $table->decimal('power_factor', 4, 2)->nullable()->default(1.0); // PF
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('power_readings');
    }
};

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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('slot_number'); // 1, 2, atau 3
            $table->unsignedInteger('nominal_paid'); // e.g. 1000, 2000, 5000
            $table->unsignedInteger('duration_seconds'); // e.g. 900 (15 menit)
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled', 'force_stopped', 'emergency_stopped'])->default('active');
            $table->string('payment_reference')->nullable(); // ID dari Mayar/QRIS
            $table->string('customer_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

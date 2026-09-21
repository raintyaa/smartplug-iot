<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'slot_number',
        'nominal_paid',
        'duration_seconds',
        'started_at',
        'ended_at',
        'status',
        'payment_reference',
        'customer_name',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'nominal_paid' => 'integer',
        'duration_seconds' => 'integer',
    ];

    /**
     * Scope untuk transaksi yang berhasil/selesai
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'active']);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PowerReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'voltage',
        'current',
        'power',
        'energy',
        'frequency',
        'power_factor',
        'recorded_at',
    ];

    protected $casts = [
        'voltage' => 'float',
        'current' => 'float',
        'power' => 'float',
        'energy' => 'float',
        'frequency' => 'float',
        'power_factor' => 'float',
        'recorded_at' => 'datetime',
    ];
}

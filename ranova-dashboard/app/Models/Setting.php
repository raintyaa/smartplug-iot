<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'label',
        'description',
    ];

    /**
     * Helper untuk mengambil nilai setting dengan default fallback
     */
    public static function get($key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Helper untuk menyimpan nilai setting
     */
    public static function set($key, $value, $label = null, $description = null)
    {
        return static::updateOrCreate(
            ['key' => $key],
            array_filter([
                'value' => $value,
                'label' => $label,
                'description' => $description,
            ])
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppMetric extends Model
{
    protected $fillable = [
        'app_id',
        'time_slot',
        'report_date',
        'ip_51la',
        'total_install',
        'total_click',
        'click_ratio',
        'ip_click_ratio',
        'conversion_rate',
        'interval_ip',
        'interval_install',
        'interval_click',
        'interval_click_ratio',
        'interval_ip_click_ratio',
        'interval_conversion_rate',
    ];

    protected $casts = [
        'report_date'              => 'date',
        'click_ratio'              => 'float',
        'ip_click_ratio'           => 'float',
        'conversion_rate'          => 'float',
        'interval_click_ratio'     => 'float',
        'interval_ip_click_ratio'  => 'float',
        'interval_conversion_rate' => 'float',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    // Format conversion_rate as percentage string
    public function getConversionRateDisplayAttribute(): string
    {
        return $this->conversion_rate !== null
            ? number_format($this->conversion_rate * 100, 2) . '%'
            : '-';
    }

    public function getIntervalConversionRateDisplayAttribute(): string
    {
        return $this->interval_conversion_rate !== null
            ? number_format($this->interval_conversion_rate * 100, 2) . '%'
            : '-';
    }
}
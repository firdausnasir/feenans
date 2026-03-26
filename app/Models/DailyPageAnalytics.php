<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyPageAnalytics extends Model
{
    protected $fillable = [
        'metric_date',
        'page_key',
        'audience',
        'membership_tier',
        'hits',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'hits' => 'integer',
        ];
    }
}

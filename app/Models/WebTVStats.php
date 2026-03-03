<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebTVStats extends Model
{
    protected $table = 'web_tv_stats';
    
    protected $fillable = [
        'date',
        'live_audience',
        'total_views',
        'broadcast_duration_seconds',
        'engagement'
    ];
    
    protected $casts = [
        'date' => 'date',
    ];
}

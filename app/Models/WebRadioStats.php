<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebRadioStats extends Model
{
    protected $table = 'web_radio_stats';
    
    protected $fillable = [
        'date',
        'live_audience',
        'total_listens',
        'broadcast_duration_seconds',
        'engagement'
    ];
    
    protected $casts = [
        'date' => 'date',
    ];
}

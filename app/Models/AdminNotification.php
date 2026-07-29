<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $fillable = [
        'type',
        'digest_id',
        'headline',
        'tone',
        'payload',
        'read_at',
        'cancelled_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at')->whereNull('cancelled_at');
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }
}

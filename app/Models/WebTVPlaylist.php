<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebTVPlaylist extends Model
{
    use HasFactory;
    protected $table = 'webtv_playlists';

    protected $fillable = [
        'name',
        'description',
        'type',
        'is_active',
        'is_loop',
        'shuffle_enabled',
        'is_auto_start',
        'ant_media_stream_id',
        'sync_status',
        'last_sync_at',
        'total_duration',
        'total_items',
        'quality',
        'bitrate',
        'buffer_duration',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_loop' => 'boolean',
        'shuffle_enabled' => 'boolean',
        'is_auto_start' => 'boolean',
        'total_duration' => 'integer',
        'total_items' => 'integer',
        'bitrate' => 'integer',
        'buffer_duration' => 'integer',
        'last_sync_at' => 'datetime',
    ];

    // Relations
    public function items(): HasMany
    {
        return $this->hasMany(WebTVPlaylistItem::class, 'webtv_playlist_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLive($query)
    {
        return $query->where('type', 'live');
    }

    public function scopeScheduled($query)
    {
        return $query->where('type', 'scheduled');
    }

    public function scopeLoop($query)
    {
        return $query->where('type', 'loop');
    }

    // Accessors
    public function getFormattedDurationAttribute()
    {
        $hours = floor($this->total_duration / 3600);
        $minutes = floor(($this->total_duration % 3600) / 60);
        $seconds = $this->total_duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->sync_status) {
            'pending' => 'warning',
            'synced' => 'success',
            'error' => 'danger',
            default => 'secondary',
        };
    }

    // Méthodes utilitaires
    public function isLive(): bool
    {
        return $this->type === 'live';
    }

    public function isScheduled(): bool
    {
        return $this->type === 'scheduled';
    }

    public function isLoop(): bool
    {
        return $this->type === 'loop';
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function updateTotals(): void
    {
        $this->update([
            'total_items' => $this->items()->count(),
            'total_duration' => $this->items()->sum('duration'),
        ]);
    }
}

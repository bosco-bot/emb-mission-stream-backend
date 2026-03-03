<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class WebTVPlaylistItem extends Model
{
    use HasFactory;
    protected $table = 'webtv_playlist_items';

    protected $fillable = [
        'webtv_playlist_id',
        'video_file_id',
        'stream_url',
        'title',
        'artist',
        'order',
        'duration',
        'quality',
        'bitrate',
        'ant_media_item_id',
        'sync_status',
        'is_live_stream',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'order' => 'integer',
        'duration' => 'integer',
        'bitrate' => 'integer',
        'is_live_stream' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    // Relations
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(WebTVPlaylist::class);
    }

    // Scopes
    public function scopeByOrder($query)
    {
        return $query->orderBy('order');
    }

    public function scopeSynced($query)
    {
        return $query->where('sync_status', 'synced');
    }

    public function scopeLiveStreams($query)
    {
        return $query->where('is_live_stream', true);
    }

    // Accessors
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) {
            return '--:--';
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getSyncStatusTextAttribute()
    {
        return match($this->sync_status) {
            'pending' => 'En attente',
            'processing' => 'En cours',
            'synced' => 'Synchronisé',
            'error' => 'Erreur',
            default => $this->sync_status,
        };
    }

    // Méthodes utilitaires
    public function isLiveStream(): bool
    {
        return $this->is_live_stream;
    }

    public function isSynced(): bool
    {
        return $this->sync_status === 'synced';
    }

    public function markAsSynced(string $antMediaItemId, ?string $streamUrl = null): void
    {
        $this->update([
            'ant_media_item_id' => $antMediaItemId,
            'sync_status' => 'synced',
            'stream_url' => $streamUrl,
        ]);
        
        Log::info("✅ Item WebTV marqué comme synchronisé", [
            'item_id' => $this->id,
            'title' => $this->title,
            'ant_media_item_id' => $antMediaItemId,
            'stream_url' => $streamUrl
        ]);
    }

    public function markAsSyncError(): void
    {
        $this->update([
            'sync_status' => 'error',
        ]);
    }
}

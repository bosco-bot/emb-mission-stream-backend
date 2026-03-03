<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Playlist extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'is_loop',
        'is_shuffle',
        'azuracast_id',
        'sync_status',
        'last_sync_at',
        'total_duration',
        'total_items',
    ];

    protected $casts = [
        'is_loop' => 'boolean',
        'is_shuffle' => 'boolean',
        'last_sync_at' => 'datetime',
        'total_duration' => 'integer',
        'total_items' => 'integer',
        'azuracast_id' => 'integer',
    ];

    /**
     * Relation avec les éléments de la playlist
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('order');
    }

    /**
     * Relation avec les fichiers média via les items
     */
    public function mediaFiles(): BelongsToMany
    {
        return $this->belongsToMany(MediaFile::class, 'playlist_items')
                    ->withPivot(['order', 'azuracast_song_id', 'sync_status', 'duration'])
                    ->withTimestamps()
                    ->orderBy('playlist_items.order');
    }

    /**
     * Obtenir le statut de synchronisation en texte
     */
    public function getSyncStatusTextAttribute(): string
    {
        return match($this->sync_status) {
            'pending' => 'En attente',
            'synced' => 'Synchronisé',
            'error' => 'Erreur',
            default => $this->sync_status,
        };
    }

    /**
     * Obtenir la durée totale formatée
     */
    public function getTotalDurationFormattedAttribute(): string
    {
        $hours = floor($this->total_duration / 3600);
        $minutes = floor(($this->total_duration % 3600) / 60);
        $seconds = $this->total_duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%02d:%02d', $minutes, $seconds);
        }
    }

    /**
     * Marquer comme synchronisé avec AzuraCast
     */
    public function markAsSynced(int $azuracastId): void
    {
        $this->update([
            'azuracast_id' => $azuracastId,
            'sync_status' => 'synced',
            'last_sync_at' => now(),
        ]);
    }

    /**
     * Marquer comme erreur de synchronisation
     */
    public function markAsSyncError(): void
    {
        $this->update([
            'sync_status' => 'error',
            'last_sync_at' => now(),
        ]);
    }

    /**
     * Recalculer les statistiques de la playlist
     */
    public function recalculateStats(): void
    {
        $items = $this->items;
        $totalDuration = $items->sum('duration') ?? 0;
        $totalItems = $items->count();

        $this->update([
            'total_duration' => $totalDuration,
            'total_items' => $totalItems,
        ]);
    }
}

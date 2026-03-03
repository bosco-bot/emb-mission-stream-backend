<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistItem extends Model
{
    protected $fillable = [
        'playlist_id',
        'media_file_id',
        'order',
        'azuracast_song_id',
        'sync_status',
        'duration',
    ];

    protected $casts = [
        'order' => 'integer',
        'azuracast_song_id' => 'integer',
        'duration' => 'integer',
    ];

    /**
     * Relation avec la playlist
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * Relation avec le fichier média
     */
    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
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
     * Obtenir la durée formatée
     */
    public function getDurationFormattedAttribute(): string
    {
        if (!$this->duration) {
            return '--:--';
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Marquer comme synchronisé avec AzuraCast
     */
    public function markAsSynced(int $azuracastSongId): void
    {
        $this->update([
            'azuracast_song_id' => $azuracastSongId,
            'sync_status' => 'synced',
        ]);
    }

    /**
     * Marquer comme erreur de synchronisation
     */
    public function markAsSyncError(): void
    {
        $this->update([
            'sync_status' => 'error',
        ]);
    }

    /**
     * Mettre à jour la durée depuis le fichier média
     */
    public function updateDurationFromMediaFile(): void
    {
        if ($this->mediaFile && $this->mediaFile->metadata) {
            $metadata = is_string($this->mediaFile->metadata) 
                ? json_decode($this->mediaFile->metadata, true) 
                : $this->mediaFile->metadata;
            
            $duration = $metadata['duration'] ?? null;
            
            if ($duration) {
                $this->update(['duration' => $duration]);
            }
        }
    }
}

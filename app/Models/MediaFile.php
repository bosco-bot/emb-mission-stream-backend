<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_name',
        'azuracast_id',
        'file_path',
        'file_url',
        'file_type',
        'mime_type',
        'file_size',
        'file_size_formatted',
        'duration',
        'width',
        'height',
        'bitrate',
        'sample_rate',
        'channels',
        'status',
        'progress',
        'error_message',
        'bytes_uploaded',
        'bytes_total',
        'estimated_time_remaining',
        'thumbnail_path',
        'thumbnail_url',
        'has_embedded_artwork',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'bitrate' => 'integer',
        'sample_rate' => 'integer',
        'channels' => 'integer',
        'progress' => 'integer',
        'bytes_uploaded' => 'integer',
        'bytes_total' => 'integer',
        'has_embedded_artwork' => 'boolean',
        'metadata' => 'array',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    // Relations
    public function audioRelations(): HasMany
    {
        return $this->hasMany(MediaFileRelation::class, 'audio_file_id');
    }

    public function videoRelations(): HasMany
    {
        return $this->hasMany(MediaFileRelation::class, 'video_file_id');
    }

    public function thumbnailRelations(): HasMany
    {
        return $this->hasMany(MediaFileRelation::class, 'thumbnail_file_id');
    }

    public function sessionFiles(): HasMany
    {
        return $this->hasMany(UploadSessionFile::class, 'media_file_id');
    }

    // Scopes
    public function scopeAudio($query)
    {
        return $query->where('file_type', 'audio');
    }

    public function scopeVideo($query)
    {
        return $query->where('file_type', 'video');
    }

    public function scopeImage($query)
    {
        return $query->where('file_type', 'image');
    }

    public function scopeImporting($query)
    {
        return $query->where('status', 'importing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeWithErrors($query)
    {
        return $query->where('status', 'error');
    }

    // Accessors
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) {
            return null;
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->bytes_total == 0) {
            return 0;
        }

        return round(($this->bytes_uploaded / $this->bytes_total) * 100, 2);
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'uploading' => 'primary',
            'importing' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'error' => 'danger',
            default => 'secondary',
        };
    }

    public function getStatusTextAttribute()
    {
        return match($this->status) {
            'uploading' => 'En cours d\'upload',
            'importing' => 'En cours d\'importation',
            'processing' => 'En cours de traitement',
            'completed' => 'Terminé',
            'error' => 'Erreur',
            default => 'Inconnu',
        };
    }

    // Méthodes utilitaires
    public function isAudio(): bool
    {
        return $this->file_type === 'audio';
    }

    public function isVideo(): bool
    {
        return $this->file_type === 'video';
    }

    public function isImage(): bool
    {
        return $this->file_type === 'image';
    }

    public function hasThumbnail(): bool
    {
        return !empty($this->thumbnail_path) || !empty($this->thumbnail_url);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['uploading', 'importing', 'processing']);
    }
}

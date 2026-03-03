<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaFileRelation extends Model
{
    use HasFactory;

    protected $fillable = [
        'audio_file_id',
        'video_file_id',
        'thumbnail_file_id',
        'relation_type',
        'confidence_score',
        'match_method',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    // Relations
    public function audioFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'audio_file_id');
    }

    public function videoFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'video_file_id');
    }

    public function thumbnailFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'thumbnail_file_id');
    }

    // Scopes
    public function scopeAudioThumbnail($query)
    {
        return $query->where('relation_type', 'audio_thumbnail_manual');
    }

    public function scopeEmbeddedArtwork($query)
    {
        return $query->where('relation_type', 'audio_embedded_artwork');
    }

    public function scopeVideoAutoThumbnail($query)
    {
        return $query->where('relation_type', 'video_auto_thumbnail');
    }

    public function scopeStandaloneImage($query)
    {
        return $query->where('relation_type', 'image_standalone');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    // Accessors
    public function getConfidencePercentageAttribute()
    {
        return round($this->confidence_score * 100, 1);
    }

    public function getRelationTypeTextAttribute()
    {
        return match($this->relation_type) {
            'audio_thumbnail_manual' => 'Audio + Thumbnail manuel',
            'audio_embedded_artwork' => 'Audio avec artwork embarqué',
            'video_auto_thumbnail' => 'Vidéo + Thumbnail automatique',
            'image_standalone' => 'Image standalone',
            default => 'Inconnu',
        };
    }

    public function getMatchMethodTextAttribute()
    {
        return match($this->match_method) {
            'exact_filename' => 'Correspondance exacte par nom',
            'filename_with_suffix' => 'Correspondance avec suffixe',
            'embedded_artwork' => 'Artwork embarqué',
            'auto_generated' => 'Généré automatiquement',
            'manual' => 'Sélection manuelle',
            default => 'Inconnu',
        };
    }

    // Méthodes utilitaires
    public function isAudioThumbnail(): bool
    {
        return $this->relation_type === 'audio_thumbnail_manual';
    }

    public function isEmbeddedArtwork(): bool
    {
        return $this->relation_type === 'audio_embedded_artwork';
    }

    public function isVideoAutoThumbnail(): bool
    {
        return $this->relation_type === 'video_auto_thumbnail';
    }

    public function isStandaloneImage(): bool
    {
        return $this->relation_type === 'image_standalone';
    }

    public function isHighConfidence(): bool
    {
        return $this->confidence_score >= 0.8;
    }

    public function isMediumConfidence(): bool
    {
        return $this->confidence_score >= 0.5 && $this->confidence_score < 0.8;
    }

    public function isLowConfidence(): bool
    {
        return $this->confidence_score < 0.5;
    }

    // Méthodes statiques pour créer des relations
    public static function createAudioThumbnailRelation(MediaFile $audio, MediaFile $thumbnail, float $confidence = 1.0, string $method = 'manual'): self
    {
        return self::create([
            'audio_file_id' => $audio->id,
            'thumbnail_file_id' => $thumbnail->id,
            'relation_type' => 'audio_thumbnail_manual',
            'confidence_score' => $confidence,
            'match_method' => $method,
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    public static function createVideoAutoThumbnailRelation(MediaFile $video): self
    {
        return self::create([
            'video_file_id' => $video->id,
            'relation_type' => 'video_auto_thumbnail',
            'confidence_score' => 1.0,
            'match_method' => 'auto_generated',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    public static function createEmbeddedArtworkRelation(MediaFile $audio): self
    {
        return self::create([
            'audio_file_id' => $audio->id,
            'relation_type' => 'audio_embedded_artwork',
            'confidence_score' => 1.0,
            'match_method' => 'embedded_artwork',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    public static function createStandaloneImageRelation(MediaFile $image): self
    {
        return self::create([
            'thumbnail_file_id' => $image->id,
            'relation_type' => 'image_standalone',
            'confidence_score' => 1.0,
            'match_method' => 'manual',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadSessionFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'media_file_id',
        'upload_order',
        'file_group',
        'suggested_thumbnail_id',
        'match_confidence',
        'match_method',
        'session_status',
        'error_message',
    ];

    protected $casts = [
        'upload_order' => 'integer',
        'match_confidence' => 'decimal:2',
        'session_status' => 'string',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    // Relations
    public function session(): BelongsTo
    {
        return $this->belongsTo(UploadSession::class, 'session_id');
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'media_file_id');
    }

    public function suggestedThumbnail(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'suggested_thumbnail_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('session_status', 'pending');
    }

    public function scopeUploading($query)
    {
        return $query->where('session_status', 'uploading');
    }

    public function scopeCompleted($query)
    {
        return $query->where('session_status', 'completed');
    }

    public function scopeWithErrors($query)
    {
        return $query->where('session_status', 'error');
    }

    public function scopeCancelled($query)
    {
        return $query->where('session_status', 'cancelled');
    }

    public function scopeInGroup($query, string $group)
    {
        return $query->where('file_group', $group);
    }

    public function scopeByOrder($query)
    {
        return $query->orderBy('upload_order');
    }

    // Accessors
    public function getConfidencePercentageAttribute()
    {
        return round($this->match_confidence * 100, 1);
    }

    public function getSessionStatusBadgeAttribute()
    {
        return match($this->session_status) {
            'pending' => 'secondary',
            'uploading' => 'primary',
            'completed' => 'success',
            'error' => 'danger',
            'cancelled' => 'warning',
            default => 'secondary',
        };
    }

    public function getSessionStatusTextAttribute()
    {
        return match($this->session_status) {
            'pending' => 'En attente',
            'uploading' => 'En cours d\'upload',
            'completed' => 'Terminé',
            'error' => 'Erreur',
            'cancelled' => 'Annulé',
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
    public function isPending(): bool
    {
        return $this->session_status === 'pending';
    }

    public function isUploading(): bool
    {
        return $this->session_status === 'uploading';
    }

    public function isCompleted(): bool
    {
        return $this->session_status === 'completed';
    }

    public function hasError(): bool
    {
        return $this->session_status === 'error';
    }

    public function isCancelled(): bool
    {
        return $this->session_status === 'cancelled';
    }

    public function isProcessing(): bool
    {
        return in_array($this->session_status, ['pending', 'uploading']);
    }

    public function hasSuggestedThumbnail(): bool
    {
        return !is_null($this->suggested_thumbnail_id);
    }

    public function isHighConfidence(): bool
    {
        return $this->match_confidence >= 0.8;
    }

    public function isMediumConfidence(): bool
    {
        return $this->match_confidence >= 0.5 && $this->match_confidence < 0.8;
    }

    public function isLowConfidence(): bool
    {
        return $this->match_confidence < 0.5;
    }

    // Méthodes pour changer le statut
    public function markAsUploading(): void
    {
        $this->update(['session_status' => 'uploading']);
    }

    public function markAsCompleted(): void
    {
        $this->update(['session_status' => 'completed']);
    }

    public function markAsError(string $errorMessage): void
    {
        $this->update([
            'session_status' => 'error',
            'error_message' => $errorMessage,
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update(['session_status' => 'cancelled']);
    }

    // Méthodes statiques
    public static function createForSession(UploadSession $session, MediaFile $file, int $order = 0, string $group = null): self
    {
        return self::create([
            'session_id' => $session->id,
            'media_file_id' => $file->id,
            'upload_order' => $order,
            'file_group' => $group,
            'session_status' => 'pending',
        ]);
    }
}

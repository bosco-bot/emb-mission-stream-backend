<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class UploadSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_token',
        'user_id',
        'total_files',
        'uploaded_files',
        'completed_files',
        'failed_files',
        'status',
        'progress',
        'total_size',
        'uploaded_size',
        'estimated_time_remaining',
        'auto_match_thumbnails',
        'generate_missing_thumbnails',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'total_files' => 'integer',
        'uploaded_files' => 'integer',
        'completed_files' => 'integer',
        'failed_files' => 'integer',
        'progress' => 'integer',
        'total_size' => 'integer',
        'uploaded_size' => 'integer',
        'auto_match_thumbnails' => 'boolean',
        'generate_missing_thumbnails' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    // Relations
    public function sessionFiles(): HasMany
    {
        return $this->hasMany(UploadSessionFile::class, 'session_id');
    }

    // Boot method to generate session token
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->session_token)) {
                $model->session_token = Str::random(64);
            }
            
            if (empty($model->started_at)) {
                $model->started_at = now();
            }
            
            if (empty($model->expires_at)) {
                $model->expires_at = now()->addHours(24); // Expire après 24h
            }
        });
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    // Accessors
    public function getProgressPercentageAttribute()
    {
        if ($this->total_files == 0) {
            return 0;
        }

        return round(($this->completed_files / $this->total_files) * 100, 2);
    }

    public function getSizeFormattedAttribute()
    {
        return $this->formatBytes($this->total_size);
    }

    public function getUploadedSizeFormattedAttribute()
    {
        return $this->formatBytes($this->uploaded_size);
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'active' => 'primary',
            'processing' => 'warning',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary',
        };
    }

    public function getStatusTextAttribute()
    {
        return match($this->status) {
            'active' => 'Active',
            'processing' => 'En cours de traitement',
            'completed' => 'Terminé',
            'failed' => 'Échec',
            'cancelled' => 'Annulé',
            default => 'Inconnu',
        };
    }

    // Méthodes utilitaires
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function updateProgress(): void
    {
        $this->progress = $this->getProgressPercentageAttribute();
        
        if ($this->completed_files >= $this->total_files) {
            $this->status = 'completed';
            $this->completed_at = now();
        }
        
        $this->save();
    }

    public function addFile(MediaFile $file, int $order = 0, string $group = null): UploadSessionFile
    {
        return $this->sessionFiles()->create([
            'media_file_id' => $file->id,
            'upload_order' => $order,
            'file_group' => $group,
            'session_status' => 'pending',
        ]);
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

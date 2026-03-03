<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebTVStream extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "ant_media_stream_id",
        "title",
        "description", 
        "status",
        "start_time",
        "end_time",
        "playback_url",
        "webrtc_url",
        "thumbnail_url",
        "embed_code",
        "is_featured",
        "metadata",
    ];

    protected $table = "webtv_streams";
    protected $casts = [
        "start_time" => "datetime",
        "end_time" => "datetime", 
        "is_featured" => "boolean",
        "metadata" => "array",
    ];

    public function scopeLive($query)
    {
        return $query->where("status", "live");
    }

    public function scopeFeatured($query)
    {
        return $query->where("is_featured", true);
    }

    public function scopeMain($query)
    {
        return $query->featured()->live()->orderBy("updated_at", "desc");
    }

    public function isLive(): bool
    {
        return $this->status === "live";
    }

    public function isOffline(): bool
    {
        return $this->status === "offline";
    }

    public function updateStatus(string $status): bool
    {
        $this->status = $status;
        
        if ($status === "live" && !$this->start_time) {
            $this->start_time = now();
        }
        
        if ($status === "finished" && $this->start_time && !$this->end_time) {
            $this->end_time = now();
        }

        return $this->save();
    }

    public function syncWithAntMedia(array $antMediaData): bool
    {
        $this->ant_media_stream_id = $antMediaData["streamId"] ?? $this->ant_media_stream_id;
        $this->title = $antMediaData["name"] ?? $this->title;
        $this->description = $antMediaData["description"] ?? $this->description;
        
        $antMediaStatus = $antMediaData["status"] ?? "offline";
        $this->status = $antMediaStatus === "broadcasting" ? "live" : "offline";
        
        if (isset($antMediaData["streamId"])) {
            $streamId = $antMediaData["streamId"];
            $this->playback_url = "https://tv.embmission.com/webtv-live/streams/{$streamId}.m3u8";
            $this->webrtc_url = "wss://tv.embmission.com/webtv-live/{$streamId}";
            $this->thumbnail_url = "https://tv.embmission.com/webtv-live/streams/{$streamId}.png";
        }

        return $this->save();
    }

    public function toApiArray(): array
    {
        return [
            "id" => $this->id,
            "stream_id" => $this->ant_media_stream_id,
            "title" => $this->title,
            "description" => $this->description,
            "status" => $this->status,
            "is_live" => $this->isLive(),
            "start_time" => $this->start_time?->toISOString(),
            "end_time" => $this->end_time?->toISOString(),
            "playback_url" => $this->playback_url,
            "webrtc_url" => $this->webrtc_url,
            "thumbnail_url" => $this->thumbnail_url,
            "embed_code" => $this->embed_code,
            "is_featured" => $this->is_featured,
            "created_at" => $this->created_at?->toISOString(),
            "updated_at" => $this->updated_at?->toISOString(),
        ];
    }
}

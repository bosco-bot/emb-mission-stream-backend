<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $mode;
    public $streamId;
    public $streamName;
    public $url;
    public $streamUrl;
    public $timestamp;
    public $itemTitle;
    public $itemId;
    public $currentTime;
    public $duration;
    public $isFinished;

    /**
     * Create a new event instance.
     */
    public function __construct(array $streamData)
    {
        $this->mode = $streamData['mode'] ?? 'vod';
        $this->streamId = $streamData['stream_id'] ?? null;
        $this->streamName = $streamData['stream_name'] ?? null;
        $this->url = $streamData['url'] ?? null;
        $this->streamUrl = $streamData['stream_url'] ?? null;
        $this->timestamp = $streamData['sync_timestamp'] ?? time();
        $this->itemTitle = $streamData['item_title'] ?? null;
        $this->itemId = $streamData['item_id'] ?? null;
        $this->currentTime = $streamData['current_time'] ?? 0.0;
        $this->duration = $streamData['duration'] ?? null;
        $this->isFinished = $streamData['is_finished'] ?? false;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('webtv-stream-status');
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'stream.status.changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'mode' => $this->mode,
            'stream_id' => $this->streamId,
            'stream_name' => $this->streamName,
            'url' => $this->url,
            'stream_url' => $this->streamUrl,
            'sync_timestamp' => $this->timestamp,
            'item_title' => $this->itemTitle,
            'item_id' => $this->itemId,
            'current_time' => $this->currentTime,
            'duration' => $this->duration,
            'is_finished' => $this->isFinished,
        ];
    }
}


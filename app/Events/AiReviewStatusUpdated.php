<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiReviewStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $reviewId,
        public readonly string $requestedBy,
        public readonly string $status,
        public readonly string $type,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->requestedBy.'.ai-reviews'),
        ];
    }

    /** @return array{review_id: string, status: string, type: string} */
    public function broadcastWith(): array
    {
        return [
            'review_id' => $this->reviewId,
            'status' => $this->status,
            'type' => $this->type,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}

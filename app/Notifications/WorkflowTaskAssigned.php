<?php

namespace App\Notifications;

use App\Models\SmartTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowTaskAssigned extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public readonly SmartTask $task) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $storedUrl = data_get($this->task->metadata, 'action_url');
        $actionUrl = is_string($storedUrl) && str_starts_with($storedUrl, '/') && ! str_starts_with($storedUrl, '//')
            ? $storedUrl
            : null;

        return [
            'title' => $this->task->title,
            'message' => $this->task->description,
            'priority' => $this->task->priority,
            'url' => $actionUrl ?? ($this->task->type === 'INTEGRATION_FAILURE'
                ? '/integrations'
                : ($this->task->activity_id ? '/activities/'.$this->task->activity_id : '/dashboard')),
        ];
    }
}

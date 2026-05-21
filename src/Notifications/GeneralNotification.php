<?php

namespace Kindharika\ApiStarter\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Kindharika\ApiStarter\Base\BaseModel;

class GeneralNotification extends Notification
{
    use Queueable;

    protected BaseModel $notification;

    public function __construct(BaseModel $notification)
    {
        $this->notification = $notification;
    }

    public function via(mixed $notifiable): array
    {
        return ['fcm'];
    }

    public function toFcm(mixed $notifiable): object
    {
        return (object) [
            'notification' => [
                'title' => $this->notification->title ?? 'Notification',
                'body' => $this->notification->body ?? '',
                'content_available' => true,
                'priority' => 'high',
            ],
            'data' => $this->notification->toArray(),
        ];
    }
}

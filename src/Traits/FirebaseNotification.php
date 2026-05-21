<?php

namespace Kindharika\ApiStarter\Traits;

use Kindharika\ApiStarter\Base\BaseModel;
use Kindharika\ApiStarter\Notifications\GeneralNotification;

trait FirebaseNotification
{
    public function sendNotification(BaseModel $user, string $title, string $body, string $type = 'INFO', ?string $imageUrl = null): BaseModel
    {
        $notificationClass = config('api-starter.notification_model', BaseModel::class);

        $notification = $notificationClass::create([
            'type'        => $type,
            'title'       => $title,
            'body'        => $body,
            'receiver_id' => method_exists($user, 'getKey') ? $user->getKey() : $user->id,
            'image_url'   => $imageUrl,
        ]);

        $user->notify(new GeneralNotification($notification));

        return $notification;
    }
}

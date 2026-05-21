<?php

namespace Kindharika\ApiStarter\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class FCMChannel
{
    protected string $endpoint;

    public function __construct()
    {
        $this->endpoint = config('api-starter.fcm.endpoint', 'https://fcm.googleapis.com/fcm/send');
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        $key = config('api-starter.fcm.server_key');
        $token = $notifiable->fcm_token ?? null;
        $message = $notification->toFCM($notifiable);

        $payloads = [
            'to'           => $token,
            'priority'     => $message->priority ?? 'high',
            'notification' => $message->notification ?? null,
            'data'         => $message->data ?? null,
        ];

        if (property_exists($message, 'timeToLive') && $message->timeToLive !== null && $message->timeToLive >= 0) {
            $payloads['time_to_live'] = (int) $message->timeToLive;
        }

        $headers = [
            'Authorization' => 'key=' . $key,
            'Content-Type'  => 'application/json',
        ];

        if (!blank($token) && !blank($key)) {
            $result = Http::withHeaders($headers)->post($this->endpoint, $payloads);

            if (property_exists($message, 'data') && method_exists($message->data, 'update')) {
                $message->data->update([
                    'fcm_multicast_id'  => $result->json('multicast_id'),
                    'fcm_success'       => $result->json('success'),
                    'fcm_canonical_ids' => $result->json('canonical_ids'),
                    'fcm_results'       => json_encode($result->json('results')),
                ]);
            }
        }
    }
}

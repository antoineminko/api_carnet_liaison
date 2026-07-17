<?php

namespace App\Services;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;

class PushNotificationService
{
    protected $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    public function sendToToken($token, $title, $body, $data = [])
    {
        if (empty($token)) {
            \Log::warning('[Push] sendToToken: token vide, envoi annulé. Title=' . $title);
            return false;
        }

        \Log::info('[Push] sendToToken: dispatching job vers token=' . substr($token, 0, 20) . '... title=' . $title);

        \App\Jobs\SendPushNotificationJob::dispatch($token, $title, $body, $data);
        return true;
    }

    /**
     * Envoyer une notification push et l'enregistrer en base de données.
     */
    public function sendAndSave($userType, $userId, $token, $title, $body, $data = [])
    {
        // Enregistrer en base
        $notification = \App\Models\Notification::create([
            'user_type' => $userType,
            'user_id' => $userId,
            'type' => $data['type'] ?? 'general',
            'title' => $title,
            'message' => $body,
            'data' => $data,
        ]);

        $data['notification_id'] = (string) $notification->id;

        return $this->sendToToken($token, $title, $body, $data);
    }

    /**
     * Envoyer une notification push sans l'enregistrer en base de données.
     */
    public function sendPushOnly($token, $title, $body, $data = [])
    {
        return $this->sendToToken($token, $title, $body, $data);
    }
}

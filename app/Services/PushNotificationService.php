<?php

namespace App\Services;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationService
{
    protected $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Envoyer une notification push à un token donné.
     *
     * @param string $token Le FCM token du destinataire
     * @param string $title Titre de la notification
     * @param string $body Corps de la notification
     * @param array $data Données additionnelles
     * @return bool
     */
    public function sendToToken($token, $title, $body, $data = [])
    {
        if (empty($token)) {
            return false;
        }

        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);
            return true;
        } catch (\Exception $e) {
            \Log::error('Erreur Push Notification: ' . $e->getMessage());
            return false;
        }
    }
}

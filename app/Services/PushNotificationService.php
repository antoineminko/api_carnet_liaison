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

            // Firebase FCM exige que toutes les valeurs dans "data" soient des chaînes de caractères (string).
            $stringifiedData = array_map('strval', $data);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($stringifiedData);

            $this->messaging->send($message);
            return true;
        } catch (\Exception $e) {
            \Log::error('Erreur Push Notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer une notification push et l'enregistrer en base de données.
     */
    public function sendAndSave($userType, $userId, $token, $title, $body, $data = [])
    {
        // Enregistrer en base
        \App\Models\Notification::create([
            'user_type' => $userType,
            'user_id' => $userId,
            'type' => $data['type'] ?? 'general',
            'title' => $title,
            'message' => $body,
            'data' => $data,
        ]);

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

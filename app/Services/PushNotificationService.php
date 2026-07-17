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
            \Log::warning('[Push] sendToToken: token vide, envoi annulé. Title=' . $title);
            return false;
        }

        \Log::info('[Push] sendToToken: envoi vers token=' . substr($token, 0, 20) . '... title=' . $title);

        try {
            $notification = Notification::create($title, $body);

            // Firebase FCM exige que toutes les valeurs dans "data" soient des chaînes de caractères (string).
            $stringifiedData = array_map('strval', $data);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($stringifiedData);

            $this->messaging->send($message);
            \Log::info('[Push] sendToToken: succès pour token=' . substr($token, 0, 20) . '...');
            return true;
        } catch (\Exception $e) {
            \Log::error('[Push] Erreur Push Notification: ' . $e->getMessage() . ' | Token: ' . substr($token, 0, 20) . '...');
            return false;
        }
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

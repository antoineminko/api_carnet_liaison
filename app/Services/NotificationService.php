<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $messaging;

    public function __construct()
    {
        $credentialsPath = storage_path('app/firebase/firebase_credentials.json');
        
        if (file_exists($credentialsPath)) {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
        } else {
            Log::warning("Fichier firebase_credentials.json introuvable.");
        }
    }

    /**
     * Envoyer une notification Push à un token spécifique
     *
     * @param string $token Token FCM du téléphone
     * @param string $title Titre de la notification
     * @param string $body Corps du message
     * @param array $data Données additionnelles
     * @return bool
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [])
    {
        if (!$this->messaging) {
            Log::error("NotificationService: Service Firebase non initialisé.");
            return false;
        }

        try {
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);
            Log::info("Notification envoyée au token: $token");
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi de la notification: " . $e->getMessage());
            return false;
        }
    }
}

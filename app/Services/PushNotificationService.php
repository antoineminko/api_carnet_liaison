<?php

namespace App\Services;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use App\Models\ParentUser;
use App\Models\Enseignant;

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

        \Log::info('[Push] sendToToken: envoi synchrone vers token=' . substr($token, 0, 20) . '... title=' . $title);

        try {
            $notification = Notification::create($title, $body);
            $stringifiedData = [];
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $stringifiedData[$key] = json_encode($value);
                } elseif (is_bool($value)) {
                    $stringifiedData[$key] = $value ? 'true' : 'false';
                } else {
                    $stringifiedData[$key] = (string) $value;
                }
            }
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($stringifiedData);

            $this->messaging->send($message);
            \Log::info('[Push] sendToToken: succès pour token=' . substr($token, 0, 20) . '...');
            return true;

        } catch (NotFound $e) {
            \Log::error('[Push] Token invalide/expiré, suppression: ' . substr($token, 0, 20) . '... | ' . $e->getMessage());
            $this->removeInvalidToken($token);
            return false;
        } catch (MessagingException $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'unregistered') || str_contains($msg, 'invalid')) {
                \Log::error('[Push] Token Unregistered, suppression: ' . substr($token, 0, 20) . '...');
                $this->removeInvalidToken($token);
            } else {
                \Log::error('[Push] Erreur FCM temporaire (réseau?): ' . $e->getMessage());
            }
            return false;
        } catch (\Exception $e) {
            \Log::error('[Push] Erreur générale: ' . $e->getMessage());
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

    protected function removeInvalidToken($token)
    {
        try {
            ParentUser::where('fcm_token', $token)->update(['fcm_token' => null]);
            Enseignant::where('fcm_token', $token)->update(['fcm_token' => null]);
            \Log::info('[Push] Nettoyage réussi du token expiré en base.');
        } catch (\Exception $e) {
            \Log::error('[Push] Erreur lors du nettoyage du token: ' . $e->getMessage());
        }
    }
}


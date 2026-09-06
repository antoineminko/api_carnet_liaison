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
    protected static $sentPushHashes = [];

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    public function sendToToken($token, $title, $body, $data = [], $badgeCount = 1)
    {
        if (empty($token)) {
            \Log::warning('[Push] sendToToken: token vide, envoi annulé. Title=' . $title);
            return false;
        }

        /* Anti-doublon : Évite d'envoyer la même notification push plusieurs fois au même token durant la même requête */
        $hash = md5($token . $title . $body);
        if (isset(self::$sentPushHashes[$hash])) {
            \Log::info("[Push] Anti-doublon déclenché pour le token " . substr($token, 0, 10) . "...");
            return true;
        }
        self::$sentPushHashes[$hash] = true;

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

            $apnsConfig = \Kreait\Firebase\Messaging\ApnsConfig::fromArray([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'badge' => $badgeCount,
                        'sound' => 'default',
                        'content-available' => 1,
                    ],
                ],
            ]);

            $androidConfig = \Kreait\Firebase\Messaging\AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',
                ],
            ]);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($stringifiedData)
                ->withApnsConfig($apnsConfig)
                ->withAndroidConfig($androidConfig);

            $this->messaging->send($message);
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

    /* Emission du Push FCM et persistance de la notification dans l'historique utilisateur */
    public function sendAndSave($userType, $userId, $token, $title, $body, $data = [])
    {
        $notification = \App\Models\Notification::create([
            'user_type' => $userType,
            'user_id' => $userId,
            'type' => $data['type'] ?? 'general',
            'title' => $title,
            'message' => $body,
            'data' => $data,
        ]);

        $data['notification_id'] = (string) $notification->id;

        /* Calcul du badge global pour l'utilisateur */
        $unreadCount = \App\Models\Notification::where('user_type', $userType)
            ->where('user_id', $userId)
            ->where('is_read', false)->count();
        
        $adminInfoCount = 0;
        if ($userType === 'parent') {
            $elevesIds = \Illuminate\Support\Facades\DB::table('eleve_parents')
                ->where('parent_id', $userId)
                ->pluck('eleve_id')->toArray();
            
            if (!empty($elevesIds)) {
                $adminInfoCount = \Illuminate\Support\Facades\DB::table('admin_informations')
                    ->whereIn('eleve_id', $elevesIds)
                    ->where('is_read', false)->count();
            }
        }

        $badgeCount = $unreadCount + $adminInfoCount;

        return $this->sendToToken($token, $title, $body, $data, $badgeCount);
    }

    /* Emission d'un Push FCM éphémère (sans persistance en base de données) */
    public function sendPushOnly($token, $title, $body, $data = [])
    {
        return $this->sendToToken($token, $title, $body, $data);
    }

    protected function removeInvalidToken($token)
    {
        try {
            ParentUser::where('fcm_token', $token)->update(['fcm_token' => null]);
            Enseignant::where('fcm_token', $token)->update(['fcm_token' => null]);
        } catch (\Exception $e) {
            \Log::error('[Push] Erreur lors du nettoyage du token: ' . $e->getMessage());
        }
    }
}


<?php

namespace App\Jobs;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ParentUser;
use App\Models\Enseignant;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $token;
    public $title;
    public $body;
    public $data;
    public $badgeCount;

    public $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct($token, $title, $body, $data = [], $badgeCount = 1)
    {
        $this->token = $token;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
        $this->badgeCount = $badgeCount;
    }

    public function handle(Messaging $messaging): void
    {
        if (empty($this->token)) {
            Log::warning('[SendPushNotificationJob] Token vide, annulation du job.');
            return;
        }

        try {
            $notification = Notification::create($this->title, $this->body);

            $stringifiedData = [];
            foreach ($this->data as $key => $value) {
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
                        'badge' => (int) $this->badgeCount,
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

            $message = CloudMessage::withTarget('token', $this->token)
                ->withNotification($notification)
                ->withData($stringifiedData)
                ->withApnsConfig($apnsConfig)
                ->withAndroidConfig($androidConfig);

            $messaging->send($message);
        } catch (NotFound $e) {
            Log::error('[SendPushNotificationJob] Token NotFound: ' . $e->getMessage() . ' | Suppression du token: ' . substr($this->token, 0, 20) . '...');
            $this->removeInvalidToken($this->token);
        } catch (InvalidMessage $e) {
            Log::error('[SendPushNotificationJob] Token InvalidMessage: ' . $e->getMessage() . ' | Suppression du token: ' . substr($this->token, 0, 20) . '...');
            $this->removeInvalidToken($this->token);
        } catch (MessagingException $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'unregistered') || str_contains($msg, 'invalid')) {
                Log::error('[SendPushNotificationJob] Token Unregistered/Invalid: ' . $e->getMessage() . ' | Suppression du token: ' . substr($this->token, 0, 20) . '...');
                $this->removeInvalidToken($this->token);
            } else {
                Log::error('[SendPushNotificationJob] Erreur FCM réseau/serveur temporaire: ' . $e->getMessage());
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('[SendPushNotificationJob] Erreur générale (réseau/VPN?): ' . $e->getMessage());
            throw $e;
        }
    }

    protected function removeInvalidToken($token): void
    {
        try {
            ParentUser::where('fcm_token', $token)->update(['fcm_token' => null]);
            Enseignant::where('fcm_token', $token)->update(['fcm_token' => null]);
        } catch (\Exception $e) {
            Log::error('[SendPushNotificationJob] Erreur lors du nettoyage du token: ' . $e->getMessage());
        }
    }
}

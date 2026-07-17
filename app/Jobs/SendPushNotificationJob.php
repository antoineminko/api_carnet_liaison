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
    
    // Le nombre de retries avant d'abandonner
    public $tries = 3;
    
    // Backoff : on attend 10s, puis 30s, puis 60s entre chaque échec réseau
    public function backoff()
    {
        return [10, 30, 60];
    }

    public function __construct($token, $title, $body, $data = [])
    {
        $this->token = $token;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    public function handle(Messaging $messaging)
    {
        if (empty($this->token)) {
            Log::warning('[SendPushNotificationJob] Token vide, annulation du job.');
            return;
        }

        Log::info('[SendPushNotificationJob] Tentative d\'envoi vers token=' . substr($this->token, 0, 20) . '...');

        try {
            $notification = Notification::create($this->title, $this->body);
            $stringifiedData = array_map('strval', $this->data);

            $message = CloudMessage::withTarget('token', $this->token)
                ->withNotification($notification)
                ->withData($stringifiedData);

            $messaging->send($message);
            Log::info('[SendPushNotificationJob] Succès pour token=' . substr($this->token, 0, 20) . '...');

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
                throw $e; // Remet en file d'attente
            }
        } catch (\Exception $e) {
            Log::error('[SendPushNotificationJob] Erreur générale (réseau/VPN?): ' . $e->getMessage());
            throw $e; // Remet en file d'attente pour retry
        }
    }

    protected function removeInvalidToken($token)
    {
        try {
            ParentUser::where('fcm_token', $token)->update(['fcm_token' => null]);
            Enseignant::where('fcm_token', $token)->update(['fcm_token' => null]);
            Log::info('[SendPushNotificationJob] Nettoyage réussi du token expiré en base.');
        } catch (\Exception $e) {
            Log::error('[SendPushNotificationJob] Erreur lors du nettoyage du token: ' . $e->getMessage());
        }
    }
}

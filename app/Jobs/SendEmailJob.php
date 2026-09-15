<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $to;
    public string $subject;
    public string $body;
    public ?string $attachmentPath;
    public ?string $attachmentName;
    public ?string $attachmentMime;

    public $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        string $to,
        string $subject,
        string $body,
        ?string $attachmentPath = null,
        ?string $attachmentName = null,
        ?string $attachmentMime = null
    ) {
        $this->to = $to;
        $this->subject = $subject;
        $this->body = $body;
        $this->attachmentPath = $attachmentPath;
        $this->attachmentName = $attachmentName;
        $this->attachmentMime = $attachmentMime;
    }

    public function handle(): void
    {
        try {
            Mail::raw($this->body, function ($msg) {
                $msg->to($this->to)->subject($this->subject);

                if ($this->attachmentPath && is_file($this->attachmentPath)) {
                    $options = [];
                    if ($this->attachmentName) {
                        $options['as'] = $this->attachmentName;
                    }
                    if ($this->attachmentMime) {
                        $options['mime'] = $this->attachmentMime;
                    }
                    $msg->attach($this->attachmentPath, $options);
                }
            });
        } catch (\Exception $e) {
            Log::error('[SendEmailJob] Erreur envoi email à ' . $this->to . ': ' . $e->getMessage());
            throw $e;
        }
    }
}

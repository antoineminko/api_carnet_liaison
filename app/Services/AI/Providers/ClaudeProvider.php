<?php

namespace App\Services\AI\Providers;

use App\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Exception;

class ClaudeProvider implements AiProviderInterface
{
    protected $apiKey;
    protected $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = config('services.ai.claude.key');
    }

    public function generateSummary(string $content): string
    {
        if (empty($this->apiKey)) {
            throw new Exception("Claude API key is not configured.");
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post($this->apiUrl, [
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 300,
            'system' => "Tu es un assistant pédagogique. Fais un résumé clair et concis (environ 3-4 phrases) du contenu de cours fourni.",
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ],
            'temperature' => 0.7,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['content'][0]['text'] ?? '';
        }

        throw new Exception("Failed to generate summary with Claude: " . $response->body());
    }
}

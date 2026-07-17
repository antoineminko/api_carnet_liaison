<?php

namespace App\Services\AI\Providers;

use App\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Exception;

class OpenAIProvider implements AiProviderInterface
{
    protected $apiKey;
    protected $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.ai.openai.key');
    }

    public function generateSummary(string $content): string
    {
        if (empty($this->apiKey)) {
            throw new Exception("OpenAI API key is not configured.");
        }

        $response = Http::withToken($this->apiKey)
            ->post($this->apiUrl, [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Tu es un assistant pédagogique. Fais un résumé clair et concis (environ 3-4 phrases) du contenu de cours fourni."
                    ],
                    [
                        'role' => 'user',
                        'content' => $content
                    ]
                ],
                'temperature' => 0.7,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? '';
        }

        throw new Exception("Failed to generate summary with OpenAI: " . $response->body());
    }
}

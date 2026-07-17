<?php

namespace App\Services\AI\Providers;

use App\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Exception;

class GeminiProvider implements AiProviderInterface
{
    protected $apiKey;
    protected $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.ai.gemini.key');
    }

    public function generateSummary(string $content): string
    {
        if (empty($this->apiKey)) {
            throw new Exception("Gemini API key is not configured.");
        }

        $prompt = "Tu es un assistant pédagogique. Fais un résumé clair et concis (environ 3-4 phrases) du contenu de cours suivant, de manière professionnelle :\n\n" . $content;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl . '?key=' . $this->apiKey, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 250,
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        }

        throw new Exception("Failed to generate summary with Gemini: " . $response->body());
    }
}

<?php

namespace App\Services\AI\Providers;

use App\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Exception;

class AzureOpenAIProvider implements AiProviderInterface
{
    protected $apiKey;
    protected $endpoint;
    protected $deploymentName;
    protected $apiVersion;

    public function __construct()
    {
        $this->apiKey = config('services.ai.azure.key');
        $this->endpoint = config('services.ai.azure.endpoint');
        $this->deploymentName = config('services.ai.azure.deployment');
        $this->apiVersion = config('services.ai.azure.api_version', '2023-05-15');
    }

    public function generateSummary(string $content): string
    {
        if (empty($this->apiKey) || empty($this->endpoint) || empty($this->deploymentName)) {
            throw new Exception("Azure OpenAI is not properly configured.");
        }

        $url = rtrim($this->endpoint, '/') . "/openai/deployments/" . $this->deploymentName . "/chat/completions?api-version=" . $this->apiVersion;

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
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

        throw new Exception("Failed to generate summary with Azure OpenAI: " . $response->body());
    }
}

<?php

namespace App\Services\AI;

use App\Contracts\AiProviderInterface;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\AzureOpenAIProvider;
use Exception;

class AiService
{
    protected AiProviderInterface $provider;

    public function __construct()
    {
        $providerName = config('services.ai.default', 'gemini');

        $this->provider = match (strtolower($providerName)) {
            'openai' => new OpenAIProvider(),
            'claude' => new ClaudeProvider(),
            'azure' => new AzureOpenAIProvider(),
            'gemini' => new GeminiProvider(),
            default => throw new Exception("AI Provider '{$providerName}' is not supported."),
        };
    }

    /**
     * Generate a pedagogical summary for a given content.
     */
    public function generateSummary(string $content): string
    {
        return $this->provider->generateSummary($content);
    }
}

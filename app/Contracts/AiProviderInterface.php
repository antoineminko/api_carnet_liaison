<?php

namespace App\Contracts;

interface AiProviderInterface
{
    /**
     * Generate a pedagogical summary based on the provided content.
     *
     * @param string $content The content to summarize
     * @return string The generated summary
     * @throws \Exception If the AI service fails
     */
    public function generateSummary(string $content): string;

    /**
     * Additional methods for future AI features can be added here
     * e.g., generateExercises(), analyzePerformance(), etc.
     */
}

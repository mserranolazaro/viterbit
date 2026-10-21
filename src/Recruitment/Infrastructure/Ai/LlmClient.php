<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Ai;

/**
 * Text in, text out: the smallest contract a real LLM provider would satisfy.
 */
interface LlmClient
{
    /**
     * @throws LlmRequestFailedException when the provider cannot answer
     */
    public function complete(string $prompt): string;
}

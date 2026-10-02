<?php

namespace App\Services\Marketing\Ai;

/**
 * Provider-agnostic contract for the marketing AI layer. Business logic depends
 * on THIS interface, never on a concrete provider (OpenAI / Anthropic / Gemini),
 * so providers can be swapped or added without touching controllers or services.
 *
 * Implementations must:
 *  - never invent product facts, prices, availability, discounts or metrics
 *  - return a structured, typed result (arrays), not free prose, for structured calls
 *  - record usage (tokens/cost) into marketing_ai_generations via the caller
 */
interface AiProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /** Free-form text completion. */
    public function generateText(string $prompt, array $options = []): string;

    /** Structured JSON output validated against $schema (associative array). */
    public function generateStructuredOutput(string $prompt, array $schema, array $options = []): array;

    /**
     * Multi-turn chat with optional tool/function calling. Provider-agnostic.
     *
     * @param array $messages Normalized conversation turns, each:
     *   ['role' => 'system'|'user'|'assistant'|'tool',
     *    'content' => string,
     *    // assistant turns that requested tools:
     *    'tool_calls' => [['id' => string, 'name' => string, 'arguments' => array], ...],
     *    // tool result turns:
     *    'tool_call_id' => string, 'name' => string]
     * @param array $tools Tool definitions, each:
     *   ['name' => string, 'description' => string, 'parameters' => <JSON Schema array>]
     * @param array $options ['system' => string, 'model' => string, 'temperature' => float, 'max_tokens' => int]
     *
     * @return array Normalized result:
     *   ['content' => string,
     *    'tool_calls' => [['id' => string, 'name' => string, 'arguments' => array], ...],
     *    'finish_reason' => string]
     */
    public function chat(array $messages, array $tools = [], array $options = []): array;
}

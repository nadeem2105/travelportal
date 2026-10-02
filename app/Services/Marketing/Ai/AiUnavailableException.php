<?php

namespace App\Services\Marketing\Ai;

/**
 * Thrown when an AI operation is requested but no provider is configured/enabled.
 * Callers should catch this and surface "AI not available" — never fake output.
 */
class AiUnavailableException extends \RuntimeException
{
}

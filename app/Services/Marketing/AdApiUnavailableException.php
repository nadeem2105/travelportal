<?php

namespace App\Services\Marketing;

/**
 * Thrown when an ad-platform operation cannot be performed through the official
 * API (not connected, not enabled, unsupported, or missing permission). The UI
 * surfaces this as "Not available through current API" — we never fake success.
 */
class AdApiUnavailableException extends \RuntimeException
{
}

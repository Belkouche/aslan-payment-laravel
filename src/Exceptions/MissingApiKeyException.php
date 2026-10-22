<?php

declare(strict_types=1);

namespace Aslan\Laravel\Exceptions;

use RuntimeException;

final class MissingApiKeyException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'The Aslan API key is not configured. Set ASLAN_API_KEY in your .env file '
            . '(sk_live_... or sk_test_...) or config("aslan.api_key").'
        );
    }
}

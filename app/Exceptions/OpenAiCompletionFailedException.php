<?php

namespace App\Exceptions;

use Exception;

final class OpenAiCompletionFailedException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $upstreamStatus = 502,
    ) {
        parent::__construct($message);
    }
}

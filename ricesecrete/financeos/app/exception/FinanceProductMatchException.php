<?php

declare(strict_types=1);

namespace app\exception;

use Exception;

class FinanceProductMatchException extends Exception
{
    public function __construct(
        public readonly int $matchCode,
        string $message,
        public readonly array $extra = []
    ) {
        parent::__construct($message);
    }
}

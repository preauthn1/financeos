<?php

declare(strict_types=1);

namespace app\exception;

use Exception;
use app\support\ErrorCode;

class BusinessException extends Exception
{
    public function __construct(int $code, ?string $message = null)
    {
        parent::__construct(ErrorCode::message($code, $message), $code);
    }
}

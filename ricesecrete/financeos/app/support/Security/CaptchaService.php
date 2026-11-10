<?php

declare(strict_types=1);

namespace app\support\Security;

use support\Redis;

final class CaptchaService
{
    private const PREFIX = 'captcha:';

    public function isEnabled(): bool
    {
        return filter_var(env('LOGIN_CAPTCHA_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{key: string, question: string}
     */
    public function generate(): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $key = bin2hex(random_bytes(16));
        $ttl = max(60, (int) env('LOGIN_CAPTCHA_TTL', 300));

        Redis::set(self::PREFIX . $key, (string) ($a + $b), $ttl);

        return [
            'key' => $key,
            'question' => "{$a} + {$b} = ?",
        ];
    }

    public function verify(string $key, string $answer): bool
    {
        if ($key === '' || $answer === '') {
            return false;
        }

        $cacheKey = self::PREFIX . $key;
        $expected = Redis::get($cacheKey);
        if ($expected === null) {
            return false;
        }

        Redis::del($cacheKey);

        return trim($answer) === (string) $expected;
    }
}

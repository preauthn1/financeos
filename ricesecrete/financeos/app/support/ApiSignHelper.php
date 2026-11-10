<?php

declare(strict_types=1);

namespace app\support;

use app\exception\BusinessException;
use app\service\SettingService;

class ApiSignHelper
{
    public static function verify(array $data, string $sign): void
    {
        if ($sign === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '签名无效');
        }

        unset($data['sign']);
        ksort($data);
        $secret = (string) (new SettingService())->getValue('api_sign_secret', 'akz8VEU!kru6kgk9dhp');
        $str = http_build_query($data) . $secret;

        if (!hash_equals(md5($str), $sign)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '编码不正确');
        }
    }
}

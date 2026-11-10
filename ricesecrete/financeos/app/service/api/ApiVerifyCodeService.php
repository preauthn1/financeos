<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\service\SettingService;
use app\support\ErrorCode;
use support\Redis;

class ApiVerifyCodeService
{
    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    public function sendSms(string $mobile): void
    {
        $mobile = preg_replace('/\s+/', '', $mobile) ?? '';
        $mobile = str_replace(['+86', '+'], '', $mobile);
        if (!preg_match('/^\d{7,13}$/', $mobile)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请输入正确的手机号');
        }

        $key = 'verify:sms:' . $mobile;
        if (Redis::get($key)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请勿频繁发送');
        }

        $code = (string) random_int(100000, 999999);
        $ttl = (int) $this->settingService->getValue('verify_code_ttl', 180);
        Redis::setex($key, max(60, $ttl), $code);

        if ((bool) $this->settingService->getValue('sms_debug_mode', true)) {
            Redis::setex('verify:sms:last:' . $mobile, 600, $code);
        }
    }

    public function sendEmail(string $email): void
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '邮箱格式有误');
        }

        $key = 'verify:email:' . md5($email);
        if (Redis::get($key)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请勿频繁发送');
        }

        $code = (string) random_int(100000, 999999);
        $ttl = (int) $this->settingService->getValue('verify_code_ttl', 180);
        Redis::setex($key, max(60, $ttl), $code);

        if ((bool) $this->settingService->getValue('email_debug_mode', true)) {
            Redis::setex('verify:email:last:' . md5($email), 600, $code);
        }
    }

    public function verifySms(string $mobile, string $code): bool
    {
        $mobile = preg_replace('/\s+/', '', str_replace(['+86', '+'], '', $mobile)) ?? '';
        return Redis::get('verify:sms:' . $mobile) === $code;
    }

    public function verifyEmail(string $email, string $code): bool
    {
        return Redis::get('verify:email:' . md5(trim($email))) === $code;
    }
}

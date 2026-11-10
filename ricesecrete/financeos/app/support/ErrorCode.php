<?php

declare(strict_types=1);

namespace app\support;

final class ErrorCode
{
    public const OK = 200;

    public const UNAUTHORIZED = 40101;
    public const TOKEN_INVALID = 40102;
    public const REFRESH_TOKEN_INVALID = 40103;

    public const FORBIDDEN = 40301;
    public const IP_NOT_ALLOWED = 40302;
    public const NOT_FOUND = 40401;

    public const VALIDATION_FAILED = 42201;
    public const USERNAME_OR_PASSWORD_ERROR = 42202;
    public const LOGIN_LOCKED = 42207;
    public const CAPTCHA_REQUIRED = 42208;
    public const CAPTCHA_INVALID = 42209;
    public const GOOGLE_AUTH_REQUIRED = 42210;
    public const GOOGLE_AUTH_INVALID = 42211;
    public const USERNAME_EXISTS = 42203;
    public const ROLE_SLUG_EXISTS = 42204;
    public const CANNOT_DELETE_SUPER_ADMIN = 42205;
    public const CANNOT_DELETE_LAST_SUPER_ADMIN = 42206;

    public const INTERNAL_ERROR = 50000;

  /** 会员端 Token 过期（兼容参考项目前端 refresh 逻辑） */
    public const MEMBER_TOKEN_EXPIRED = 10001;

    /**
     * @return array<int, string>
     */
    public static function messages(): array
    {
        return [
            self::OK => 'success',
            self::UNAUTHORIZED => '未登录 / Token 缺失',
            self::TOKEN_INVALID => 'Token 无效或已过期',
            self::REFRESH_TOKEN_INVALID => 'refresh_token 无效',
            self::FORBIDDEN => '无权限访问',
            self::IP_NOT_ALLOWED => 'IP 不在白名单',
            self::NOT_FOUND => '资源不存在',
            self::VALIDATION_FAILED => '参数校验失败',
            self::USERNAME_OR_PASSWORD_ERROR => '用户名或密码错误',
            self::LOGIN_LOCKED => '登录失败次数过多，账号已临时锁定',
            self::CAPTCHA_REQUIRED => '请输入验证码',
            self::CAPTCHA_INVALID => '验证码错误或已过期',
            self::GOOGLE_AUTH_REQUIRED => '请输入谷歌验证码',
            self::GOOGLE_AUTH_INVALID => '谷歌验证码错误',
            self::USERNAME_EXISTS => '用户名已存在',
            self::ROLE_SLUG_EXISTS => '角色标识已存在',
            self::CANNOT_DELETE_SUPER_ADMIN => '不能删除超级管理员',
            self::CANNOT_DELETE_LAST_SUPER_ADMIN => '不能删除最后一个超级管理员',
            self::INTERNAL_ERROR => '服务器内部错误',
            self::MEMBER_TOKEN_EXPIRED => 'Token 无效或已过期',
        ];
    }

    public static function message(int $code, ?string $fallback = null): string
    {
        if ($fallback !== null && $fallback !== '') {
            return $fallback;
        }

        return self::messages()[$code] ?? 'error';
    }
}


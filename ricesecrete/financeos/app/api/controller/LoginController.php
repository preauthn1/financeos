<?php

declare(strict_types=1);

namespace app\api\controller;

use app\middleware\MemberAuthMiddleware;
use app\service\api\MemberAuthService;
use support\Request;

class LoginController
{
    public function __construct(private ?MemberAuthService $service = null)
    {
        $this->service = $service ?? new MemberAuthService();
    }

    public function doLogin(Request $request)
    {
        $data = $this->service->login(
            (string) $request->post('login_type', 'mobile'),
            (string) ($request->post('mobile') ?: $request->post('email') ?: $request->post('account', '')),
            (string) $request->post('password', ''),
            (string) $request->post('phone_code', ''),
            (string) $request->getRealIp()
        );
        return success($data, '登录成功');
    }

    public function doReg(Request $request)
    {
        $data = $this->service->register($request->post(), (string) $request->getRealIp());
        return success($data, '注册成功');
    }

    public function refreshToken(Request $request)
    {
        $token = MemberAuthMiddleware::extractToken($request);
        if ($token === '') {
            $token = (string) $request->post('refresh_token', '');
        }
        return success($this->service->refresh($token));
    }

    public function loginOut(Request $request)
    {
        $userId = (int) ($request->user_id ?? 0);
        $this->service->logout($userId, (string) $request->post('refresh_token', ''));
        return success(null, '退出成功');
    }

    public function sendSms(Request $request, string $mobile = '')
    {
        $mobile = $mobile ?: (string) $request->get('mobile', '');
        (new \app\service\api\ApiVerifyCodeService())->sendSms($mobile);
        return success(null, '发送成功');
    }

    public function sendEmail(Request $request, string $email = '')
    {
        $email = $email ?: (string) $request->get('email', '');
        (new \app\service\api\ApiVerifyCodeService())->sendEmail($email);
        return success(null, '发送成功');
    }
}

<?php

declare(strict_types=1);

namespace app\admin\controller;

use support\Request;
use app\service\AuthService;
use app\service\AdminGoogleAuthService;
use app\support\Security\CaptchaService;

class AuthController extends BaseController
{
    protected AuthService $authService;
    protected AdminGoogleAuthService $googleAuthService;

    public function __construct(?AuthService $authService = null, ?AdminGoogleAuthService $googleAuthService = null)
    {
        $this->authService = $authService ?? new AuthService();
        $this->googleAuthService = $googleAuthService ?? new AdminGoogleAuthService();
    }

    public function login(Request $request)
    {
        $username = (string) $request->post('username', '');
        $password = (string) $request->post('password', '');

        if ($username === '' || $password === '') {
            return fail(42201, '用户名和密码不能为空');
        }

        $result = $this->authService->login($username, $password, [
            'ip' => (string) $request->getRealIp(),
            'user_agent' => (string) $request->header('user-agent', ''),
            'captcha_key' => $request->post('captcha_key'),
            'captcha_answer' => $request->post('captcha_answer'),
            'google_code' => $request->post('google_code'),
        ]);

        return success($result);
    }

    public function captcha()
    {
        $captchaService = new CaptchaService();
        if (!$captchaService->isEnabled()) {
            return success(['enabled' => false]);
        }

        $captcha = $captchaService->generate();
        return success([
            'enabled' => true,
            'key' => $captcha['key'],
            'question' => $captcha['question'],
        ]);
    }

    public function loginStatus(Request $request)
    {
        $username = (string) $request->get('username', '');
        return success([
            'captcha_required' => $username !== ''
                ? $this->authService->loginRequiresCaptcha($username, (string) $request->getRealIp())
                : false,
            'google_auth_bound' => $username !== ''
                ? $this->authService->loginRequiresGoogleAuth($username)
                : false,
        ]);
    }

    public function refresh(Request $request)
    {
        $refreshToken = (string) $request->post('refresh_token', '');
        if ($refreshToken === '') {
            return fail(42201, 'refresh_token 不能为空');
        }
        return success($this->authService->refresh($refreshToken));
    }

    public function logout(Request $request)
    {
        $refreshToken = (string) $request->post('refresh_token', '');
        $accessToken = $request->header('Authorization', '');
        if (str_starts_with($accessToken, 'Bearer ')) {
            $accessToken = substr($accessToken, 7);
        } else {
            $accessToken = null;
        }
        $this->authService->logout($refreshToken, $accessToken);
        return success(null, '登出成功');
    }

    public function me(Request $request)
    {
        return success($this->authService->getUserInfo($this->adminId($request)));
    }

    public function menus(Request $request)
    {
        $roles = (array) ($request->admin_roles ?? []);
        return success($this->authService->getMenus($this->adminId($request), $roles));
    }

    public function serverTime()
    {
        $now = time();

        return success([
            'timestamp' => $now,
            'datetime'  => date('Y-m-d H:i:s', $now),
            'timezone'  => date_default_timezone_get(),
        ]);
    }

    public function googleAuthSetup(Request $request)
    {
        return success($this->googleAuthService->createSetup($this->adminId($request)));
    }

    public function googleAuthBind(Request $request)
    {
        $code = (string) $request->post('code', '');
        if ($code === '') {
            return fail(42201, '请输入谷歌验证码');
        }

        $this->googleAuthService->bind($this->adminId($request), $code);

        return success(null, '绑定成功');
    }
}

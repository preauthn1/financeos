<?php

declare(strict_types=1);

namespace app\middleware;

use app\support\Locale;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class LocaleMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $cookieLang = (string) ($request->cookie('app_lang') ?? '');
        $queryLang = (string) ($request->get('lang', ''));
        $userLang = isset($request->user) && is_object($request->user)
            ? (string) ($request->user->lang ?? '')
            : null;

        $locale = Locale::resolveFromRequest(
            $queryLang !== '' ? $queryLang : null,
            $cookieLang !== '' ? $cookieLang : null,
            $userLang
        );
        Locale::setCurrent($locale);
        $request->locale = $locale;

        /** @var Response $response */
        $response = $handler($request);
        return $response;
    }
}

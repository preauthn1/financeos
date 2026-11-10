<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\LangExportService;
use app\service\LangService;
use app\service\SettingService;
use app\support\Locale;
use support\Request;
use Webman\Http\Response;

class LangController
{
    public function __construct(
        private ?LangService $langService = null,
        private ?LangExportService $exportService = null,
        private ?SettingService $settingService = null,
    ) {
        $this->langService = $langService ?? new LangService();
        $this->exportService = $exportService ?? new LangExportService();
        $this->settingService = $settingService ?? new SettingService();
    }

    public function index(Request $request)
    {
        return success([
            'langs' => $this->langService->publicList(),
            'current' => Locale::current(),
            'checkout_language' => (int) $this->settingService->getValue('checkout_language', 1),
            'system_default_lang' => Locale::defaultApiLocale(),
        ]);
    }

    public function checkLang(Request $request): Response
    {
        $lang = Locale::normalize((string) $request->get('lang', ''));
        $checkout = (int) $this->settingService->getValue('checkout_language', 1);
        if ($checkout === 1 && Locale::isEnabled($lang)) {
            Locale::setCurrent($lang);
            return success(null, 'ok')->cookie('app_lang', $lang, 86400 * 30);
        }
        return success(null, 'ok');
    }

    public function messages(Request $request)
    {
        $lang = Locale::normalize((string) $request->get('lang', Locale::current()));
        return success($this->exportService->loadForLocale($lang));
    }
}

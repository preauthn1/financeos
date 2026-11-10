<?php

declare(strict_types=1);

namespace app\support;

use app\model\sys\LangModel;

final class SystemConfigMeta
{
  /** @return list<array{value: string, label: string}> */
    public static function timezones(): array
    {
        $map = [
            'Pacific/Wake' => '(GMT-12:00) 国际日期线西',
            'Pacific/Apia' => '(GMT-11:00) 萨摩亚、中途岛',
            'Pacific/Honolulu' => '(GMT-10:00) 夏威夷',
            'America/Anchorage' => '(GMT-09:00) 阿拉斯加',
            'America/Los_Angeles' => '(GMT-08:00) 太平洋时间（美国和加拿大）',
            'America/Chihuahua' => '(GMT-07:00) 奇瓦瓦、拉巴斯',
            'America/Managua' => '(GMT-06:00) 中美洲',
            'America/Chicago' => '(GMT-06:00) 中部时间（美国和加拿大）',
            'America/Mexico_City' => '(GMT-06:00) 墨西哥城',
            'America/Bogota' => '(GMT-05:00) 波哥大',
            'America/New_York' => '(GMT-05:00) 美国东部时间',
            'America/Halifax' => '(GMT-04:00) 大西洋时间（加拿大）',
            'America/Caracas' => '(GMT-04:00) 加拉加斯',
            'America/Santiago' => '(GMT-04:00) 圣地亚哥',
            'America/St_Johns' => '(GMT-03:30) 纽芬兰',
            'America/Godthab' => '(GMT-03:00) 格陵兰岛',
            'America/Sao_Paulo' => '(GMT-03:00) 圣保罗',
            'America/Noronha' => '(GMT-02:00) 大西洋中部',
            'Atlantic/Azores' => '(GMT-01:00) 亚速尔群岛',
            'Europe/London' => '(GMT) 伦敦',
            'Europe/Paris' => '(GMT+01:00) 马德里、巴黎',
            'Europe/Berlin' => '(GMT+01:00) 罗马',
            'Europe/Helsinki' => '(GMT+02:00) 赫尔辛基',
            'Asia/Jerusalem' => '(GMT+02:00) 耶路撒冷',
            'Europe/Moscow' => '(GMT+03:00) 莫斯科、圣彼得堡',
            'Asia/Tehran' => '(GMT+03:30) 德黑兰',
            'Asia/Tbilisi' => '(GMT+04:00) 第比利斯、巴库',
            'Asia/Kabul' => '(GMT+04:30) 喀布尔',
            'Asia/Karachi' => '(GMT+05:00) 伊斯兰堡、卡拉奇',
            'Asia/Calcutta' => '(GMT+05:30) 孟买、新德里',
            'Asia/Katmandu' => '(GMT+05:45) 加德满都',
            'Asia/Dhaka' => '(GMT+06:00) 达卡、阿斯塔纳',
            'Asia/Rangoon' => '(GMT+06:30) 仰光',
            'Asia/Bangkok' => '(GMT+07:00) 曼谷、河内',
            'Asia/Hong_Kong' => '(GMT+08:00) 北京、新加坡',
            'Asia/Seoul' => '(GMT+09:00) 首尔',
            'Asia/Tokyo' => '(GMT+09:00) 东京',
            'Australia/Adelaide' => '(GMT+09:30) 阿德莱德',
            'Australia/Sydney' => '(GMT+10:00) 堪培拉、悉尼、墨尔本',
            'Asia/Magadan' => '(GMT+11:00) 马加丹、所罗门群岛',
            'Pacific/Auckland' => '(GMT+12:00) 奥克兰、惠灵顿',
            'Pacific/Fiji' => '(GMT+12:00) 斐济、马歇尔岛',
            'Pacific/Tongatapu' => '(GMT+13:00) 努库阿洛法',
        ];
        $result = [];
        foreach ($map as $value => $label) {
            $result[] = ['value' => $value, 'label' => $label];
        }

        return $result;
    }

  /** @return list<array{value: string, label: string}> */
    public static function phoneCodes(): array
    {
        $codes = [
            '+1', '+7', '+20', '+27', '+30', '+31', '+32', '+33', '+34', '+36', '+39', '+40', '+41', '+43', '+44',
            '+45', '+46', '+47', '+48', '+49', '+51', '+52', '+54', '+55', '+56', '+57', '+58', '+60', '+61', '+62',
            '+63', '+64', '+65', '+66', '+81', '+82', '+84', '+86', '+90', '+91', '+92', '+93', '+963', '+94', '+95',
            '+98', '+212', '+213', '+216', '+218', '+220', '+221', '+222', '+223', '+224', '+225', '+226', '+227',
            '+228', '+229', '+230', '+231', '+232', '+233', '+234', '+235', '+236', '+237', '+238', '+239', '+240',
            '+241', '+242', '+243', '+244', '+245', '+248', '+250', '+251', '+252', '+253', '+254', '+255', '+256',
            '+257', '+258', '+260', '+261', '+262', '+263', '+264', '+265', '+266', '+267', '+268', '+269', '+291',
            '+297', '+298', '+299', '+350', '+351', '+352', '+353', '+354', '+355', '+356', '+357', '+358', '+359',
            '+370', '+371', '+372', '+373', '+374', '+375', '+376', '+377', '+378', '+380', '+381', '+382', '+385',
            '+386', '+387', '+389', '+420', '+421', '+423', '+501', '+502', '+503', '+504', '+505', '+506', '+507',
            '+508', '+509', '+590', '+591', '+592', '+593', '+594', '+595', '+596', '+597', '+598', '+599', '+670',
            '+673', '+675', '+676', '+677', '+678', '+679', '+680', '+682', '+685', '+686', '+687', '+689', '+852',
            '+853', '+855', '+856', '+880', '+886', '+960', '+961', '+962', '+964', '+965', '+966', '+967', '+968',
            '+970', '+971', '+972', '+973', '+974', '+975', '+976', '+977', '+992', '+993', '+994', '+995', '+996',
            '+998',
        ];
        $result = [];
        foreach ($codes as $code) {
            $result[] = ['value' => $code, 'label' => $code];
        }

        return $result;
    }

  /** @return list<array{value: string, label: string}> */
    public static function languages(): array
    {
        return LangModel::where('status', 1)
            ->orderByDesc('is_default')
            ->orderBy('sort')
            ->get()
            ->map(static fn (LangModel $row) => [
                'value' => (string) $row->lang,
                'label' => (string) $row->title,
            ])
            ->all();
    }

  /** @return array<string, list<array{value: string, label: string}>>> */
    public static function optionSets(): array
    {
        return [
            'timezones' => self::timezones(),
            'phone_codes' => self::phoneCodes(),
            'languages' => self::languages(),
            'region_limit_fields' => [
                ['value' => 'countryCode', 'label' => '国家代码[countryCode]'],
                ['value' => 'countryName', 'label' => '国家名称[countryName]'],
                ['value' => 'regionName', 'label' => '省/区级名称[regionName]'],
                ['value' => 'cityName', 'label' => '城市名称[cityName]'],
            ],
        ];
    }
}

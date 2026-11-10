<?php

declare(strict_types=1);

namespace app\support;

use app\model\ad\AdProductOrderModel;

class FinanceProductPopupConfig
{
    public static function defaultStyle(): array
    {
        return [
            'width_value' => 90,
            'width_unit' => '%',
            'max_width' => '',
            'max_height' => '80vh',
            'padding' => '20px',
            'border_radius' => '15px',
            'background' => '#161315',
        ];
    }

    public static function parseStored(mixed $raw): array
    {
        if (!is_array($raw)) {
            return ['items' => [], 'style' => self::defaultStyle()];
        }

        if (array_is_list($raw)) {
            return ['items' => $raw, 'style' => self::defaultStyle()];
        }

        return [
            'items' => is_array($raw['items'] ?? null) ? $raw['items'] : [],
            'style' => self::normalizeStyle((array) ($raw['style'] ?? [])),
        ];
    }

    public static function normalizeStyle(array $style): array
    {
        $defaults = self::defaultStyle();
        $widthValue = (int) ($style['width_value'] ?? $defaults['width_value']);
        if ($widthValue <= 0) {
            $widthValue = (int) $defaults['width_value'];
        }

        $widthUnit = (string) ($style['width_unit'] ?? $defaults['width_unit']);
        if (!in_array($widthUnit, ['%', 'px'], true)) {
            $widthUnit = '%';
        }

        return [
            'width_value' => $widthValue,
            'width_unit' => $widthUnit,
            'max_width' => trim((string) ($style['max_width'] ?? $defaults['max_width'])),
            'max_height' => trim((string) ($style['max_height'] ?? $defaults['max_height'])),
            'padding' => trim((string) ($style['padding'] ?? $defaults['padding'])),
            'border_radius' => trim((string) ($style['border_radius'] ?? $defaults['border_radius'])),
            'background' => trim((string) ($style['background'] ?? $defaults['background'])),
        ];
    }

    public static function styleToCss(array $style): array
    {
        $normalized = self::normalizeStyle($style);
        $css = [
            'width' => $normalized['width_value'] . $normalized['width_unit'],
            'padding' => $normalized['padding'],
            'border-radius' => $normalized['border_radius'],
            'background' => $normalized['background'],
        ];
        if ($normalized['max_width'] !== '') {
            $css['max-width'] = $normalized['max_width'];
        }
        if ($normalized['max_height'] !== '') {
            $css['max-height'] = $normalized['max_height'];
            $css['overflow-y'] = 'auto';
        }

        return $css;
    }

    /** @param array<int, array<string, mixed>> $items */
    public static function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $index => $item) {
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $content = array_values(array_filter(array_map(
                static fn ($line) => trim((string) $line),
                (array) ($item['content'] ?? [])
            )));
            $normalized[] = [
                'title' => $title,
                'enabled' => !empty($item['enabled']) ? 1 : 0,
                'sort' => (int) ($item['sort'] ?? ($index + 1)),
                'content' => $content,
            ];
        }

        usort($normalized, static fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return $normalized;
    }

    /** @return array<int, array{title: string, content: string}> */
    public static function buildOtherInfo(array $items): array
    {
        $enabled = array_values(array_filter(
            self::normalizeItems($items),
            static fn ($item) => !empty($item['enabled']) && !empty($item['content'])
        ));

        $otherInfo = [];
        foreach ($enabled as $item) {
            $contents = $item['content'];
            $otherInfo[] = [
                'title' => $item['title'],
                'content' => $contents[array_rand($contents)],
            ];
        }

        return $otherInfo;
    }

    public static function formatOrderForH5(array $order): array
    {
        $otherInfo = $order['other_info'] ?? [];
        if (is_string($otherInfo)) {
            $decoded = json_decode($otherInfo, true);
            $otherInfo = is_array($decoded) ? $decoded : [];
        }

        $cycle = (float) ($order['cycle'] ?? 0);
        $periodType = (string) ($order['invest_period_type'] ?? 'day');
        $status = (int) ($order['status'] ?? 0);
        $cycleStr = self::formatCycleStr($cycle, $periodType, $status);

        $investAmount = (string) ($order['invest_amount'] ?? $order['price'] ?? '0');
        $profitAmount = (string) ($order['profit_amount'] ?? '0');

        return array_merge($order, [
            'money' => $investAmount,
            'profit_money' => $profitAmount,
            'cycle_str' => $cycleStr,
            'other_info' => $otherInfo,
        ]);
    }

    private static function formatCycleStr(float $cycle, string $periodType, int $status): string
    {
        $parts = explode('.', (string) $cycle);
        $main = $parts[0] ?? '0';

        if ($status !== AdProductOrderModel::STATUS_SETTLED) {
            return match ($periodType) {
                'minute' => '预计 ' . $main . 'm',
                'hour' => '预计 ' . $main . 'h',
                default => '预计 ' . $main . 'd',
            };
        }

        return match ($periodType) {
            'minute' => (string) ceil($cycle) . 'm',
            'hour' => self::formatHourCycle($cycle),
            default => self::formatDayCycle($cycle),
        };
    }

    private static function formatHourCycle(float $cycle): string
    {
        $hoursInt = (int) floor($cycle);
        $minutes = (int) ceil(($cycle - $hoursInt) * 60);

        return trim(($hoursInt ? $hoursInt . 'h ' : '') . ($minutes ? $minutes . 'm' : ''));
    }

    private static function formatDayCycle(float $cycle): string
    {
        $daysInt = (int) floor($cycle);
        $hoursDecimal = ($cycle - $daysInt) * 24;
        $hoursInt = (int) floor($hoursDecimal);
        $minutes = (int) ceil(($hoursDecimal - $hoursInt) * 60);

        return trim(
            ($daysInt ? $daysInt . 'd ' : '')
            . ($hoursInt ? $hoursInt . 'h ' : '')
            . ($minutes ? $minutes . 'm' : '')
        );
    }
}

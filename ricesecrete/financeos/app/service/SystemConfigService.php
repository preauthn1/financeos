<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\support\ErrorCode;
use app\support\SystemConfigMeta;

class SystemConfigService
{
    private SettingService $settingService;

  /** @var array<string, mixed>|null */
    private static ?array $schemaCache = null;

    public function __construct(?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

  /** @return array{tabs: list<array<string, mixed>>, options: array<string, mixed>, values: array<string, mixed>, client_ip: string} */
    public function bundle(): array
    {
        $schema = $this->schema();
        $keys = $this->collectKeys($schema['tabs']);
        $values = [];
        foreach ($keys as $key) {
            $default = $this->defaultForKey($schema['tabs'], $key);
            $values[$key] = $this->normalizeValue($key, $this->settingService->getValue($key, $default));
        }

        $values = $this->splitEntryDomain($values);

        return [
            'tabs' => $this->hydrateTabs($schema['tabs']),
            'options' => SystemConfigMeta::optionSets(),
            'values' => $values,
            'client_ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];
    }

  /** @param array<string, mixed> $payload */
    public function save(array $payload): void
    {
        $schema = $this->schema();
        $allowed = array_fill_keys($this->collectKeys($schema['tabs']), true);
        $payload = $this->mergeEntryDomain($payload);
        unset($payload['app_entry_domain']);

        foreach ($payload as $key => $value) {
            if (!isset($allowed[$key])) {
                continue;
            }
            if ($key === 'entry_domain') {
                $value = $this->processEntryDomain((string) $value);
            }
            $this->settingService->upsert($key, $value, $this->descriptionForKey($schema['tabs'], $key));
        }
    }

  /** @return array<string, mixed> */
    public function schema(): array
    {
        if (self::$schemaCache !== null) {
            return self::$schemaCache;
        }

        $path = base_path('config/system_config_schema.php');
        if (!is_file($path)) {
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, '系统配置 schema 不存在');
        }

        /** @var array<string, mixed> $schema */
        $schema = require $path;
        self::$schemaCache = $schema;

        return $schema;
    }

  /** @param list<array<string, mixed>> $tabs */
    private function collectKeys(array $tabs): array
    {
        $keys = [];
        foreach ($tabs as $tab) {
            foreach ($tab['fields'] ?? [] as $field) {
                $keys[] = (string) $field['key'];
            }
        }

        return array_values(array_unique($keys));
    }

  /** @param list<array<string, mixed>> $tabs */
    private function defaultForKey(array $tabs, string $key): mixed
    {
        foreach ($tabs as $tab) {
            foreach ($tab['fields'] ?? [] as $field) {
                if ((string) ($field['key'] ?? '') === $key) {
                    return $field['default'] ?? '';
                }
            }
        }

        return '';
    }

  /** @param list<array<string, mixed>> $tabs */
    private function descriptionForKey(array $tabs, string $key): string
    {
        foreach ($tabs as $tab) {
            foreach ($tab['fields'] ?? [] as $field) {
                if ((string) ($field['key'] ?? '') === $key) {
                    $help = (string) ($field['help'] ?? '');
                    $label = (string) ($field['label'] ?? $key);

                    return $help !== '' ? "{$label}（{$help}）" : $label;
                }
            }
        }

        return $key;
    }

  /** @param list<array<string, mixed>> $tabs */
    private function hydrateTabs(array $tabs): array
    {
        $options = SystemConfigMeta::optionSets();
        $result = [];
        foreach ($tabs as $tab) {
            $fields = [];
            foreach ($tab['fields'] ?? [] as $field) {
                $item = $field;
                if (!empty($field['options_ref'])) {
                    $ref = (string) $field['options_ref'];
                    $item['options'] = $options[$ref] ?? [];
                    unset($item['options_ref']);
                }
                $fields[] = $item;
            }
            $tab['fields'] = $fields;
            $result[] = $tab;
        }

        return $result;
    }

  /** @param array<string, mixed> $values */
    private function splitEntryDomain(array $values): array
    {
        $raw = trim((string) ($values['entry_domain'] ?? ''));
        if ($raw === '') {
            $values['app_entry_domain'] = '';
            $values['entry_domain'] = '';

            return $values;
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $values['app_entry_domain'] = (string) ($lines[0] ?? '');
        array_shift($lines);
        $values['entry_domain'] = trim(implode("\n", $lines));

        return $values;
    }

  /** @param array<string, mixed> $payload */
    private function mergeEntryDomain(array $payload): array
    {
        $app = trim((string) ($payload['app_entry_domain'] ?? ''));
        $entry = trim((string) ($payload['entry_domain'] ?? ''));
        if ($app !== '' || $entry !== '') {
            $payload['entry_domain'] = trim($app . "\n" . $entry);
        }

        return $payload;
    }

    private function processEntryDomain(string $entryDomain): string
    {
        if ($entryDomain === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $entryDomain) ?: [];
        $putDomain = [];
        $newEntryDomain = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $host = trim($parts[0]);
            $target = trim($parts[1]);
            if (!preg_match('/^(?:[a-z0-9]+(?:-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $host)) {
                continue;
            }
            $putDomain[$host] = $target;
            $newEntryDomain .= $host . '|' . $target . "\n";
        }

        $root = dirname(base_path());
        $savePath = $root . '/in_www/';
        if (!is_dir($savePath)) {
            mkdir($savePath, 0755, true);
        }
        file_put_contents(
            $savePath . 'domain.php',
            '<?php $host_arr =' . var_export($putDomain, true) . ';'
        );

        return trim($newEntryDomain);
    }

    private function normalizeValue(string $key, mixed $value): mixed
    {
        if ($key === 's3_config') {
            if (!is_array($value)) {
                return [
                    'credentials_key' => '',
                    'credentials_secret' => '',
                    'region' => 'ap-east-1',
                    'bucket' => '',
                    'url' => '',
                    'proxy' => null,
                    'presign_expires' => 900,
                ];
            }

            return [
                'credentials_key' => (string) ($value['credentials_key'] ?? ''),
                'credentials_secret' => (string) ($value['credentials_secret'] ?? ''),
                'region' => (string) ($value['region'] ?? 'ap-east-1'),
                'bucket' => (string) ($value['bucket'] ?? ''),
                'url' => (string) ($value['url'] ?? ''),
                'proxy' => isset($value['proxy']) && $value['proxy'] !== '' ? (string) $value['proxy'] : null,
                'presign_expires' => max(60, (int) ($value['presign_expires'] ?? 900)),
            ];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return (string) $value;
    }
}

<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\support\ErrorCode;
use Aws\S3\S3Client;
use Webman\Http\UploadFile;

class S3Service
{
    private const TYPE_RULES = [
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'max_size' => 'UPLOAD_MAX_SIZE',
            'default_max_size' => 5242880,
            'prefix' => 'images',
        ],
        'document' => [
            'extensions' => ['pdf', 'doc', 'docx'],
            'mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'max_size' => 'UPLOAD_DOCUMENT_MAX_SIZE',
            'default_max_size' => 10485760,
            'prefix' => 'documents',
        ],
        'video' => [
            'extensions' => ['mp4', 'webm', 'mov', 'mkv', 'm4v', 'avi', 'apk'],
            'mimes' => [
                'video/mp4',
                'video/webm',
                'video/quicktime',
                'video/x-matroska',
                'video/x-msvideo',
                'video/avi',
                'application/vnd.android.package-archive',
                'application/octet-stream',
            ],
            'max_size' => 'UPLOAD_VIDEO_MAX_SIZE',
            'default_max_size' => 104857600,
            'prefix' => 'video',
        ],
        // APP 安装包 / 语音等通用文件（参数配置 IOS/Android APP）
        'file' => [
            'extensions' => [
                'apk', 'ipa', 'mobileconfig', 'plist', 'zip', 'rar', '7z',
                'mp3', 'wav', 'ogg', 'm4a', 'aac',
            ],
            'mimes' => [
                'application/vnd.android.package-archive',
                'application/octet-stream',
                'application/zip',
                'application/x-zip-compressed',
                'application/xml',
                'text/xml',
                'text/plain',
                'application/x-plist',
                'application/iphone',
                'audio/mpeg',
                'audio/mp3',
                'audio/wav',
                'audio/x-wav',
                'audio/ogg',
                'audio/mp4',
                'audio/aac',
                'audio/x-m4a',
            ],
            'max_size' => 'UPLOAD_FILE_MAX_SIZE',
            'default_max_size' => 209715200,
            'prefix' => 'files',
            'allow_empty_mime' => true,
        ],
    ];

    public const ALLOWED_TYPES = ['image', 'document', 'video', 'file'];

    private ?S3Client $client = null;

    protected SettingService $settingService;

    public function __construct(?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    public function createPresignedUpload(string $filename, string $mimeType, string $type): array
    {
        $mimeType = $this->normalizeMime($mimeType, $type);
        $extension = $this->validateMeta($filename, $mimeType, 0, $type, false);
        $key = $this->generateObjectKey($type, $extension);
        $config = $this->settingService->getS3Config();

        $command = $this->client()->getCommand('PutObject', [
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'ContentType' => $mimeType !== '' ? $mimeType : 'application/octet-stream',
        ]);

        $expires = $config['presign_expires'];
        $request = $this->client()->createPresignedRequest($command, "+{$expires} seconds");

        return [
            'upload_url' => (string) $request->getUri(),
            'key' => $key,
            'url' => $this->publicUrl($key),
            'expires_in' => $expires,
        ];
    }

    public function upload(UploadFile $file, string $type): array
    {
        if (!$file->isValid()) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '文件上传失败');
        }

        $filename = (string) $file->getUploadName();
        $mimeType = $this->normalizeMime((string) $file->getUploadMimeType(), $type);
        $size = (int) $file->getSize();

        $extension = $this->validateMeta($filename, $mimeType, $size, $type, true);
        $key = $this->generateObjectKey($type, $extension);

        if ($this->isS3Configured()) {
            $this->client()->putObject([
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'SourceFile' => $file->getPathname(),
                'ContentType' => $mimeType,
            ]);

            return [
                'url' => $this->publicUrl($key),
                'key' => $key,
                'filename' => $filename,
                'size' => $size,
                'storage' => 's3',
            ];
        }

        return $this->storeLocally($file, $key, $filename, $size);
    }

    private function validateMeta(
        string $filename,
        string $mimeType,
        int $size,
        string $type,
        bool $checkSize
    ): string {
        if (!isset(self::TYPE_RULES[$type])) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '不支持的文件类型参数');
        }

        $rules = self::TYPE_RULES[$type];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, $rules['extensions'], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '不支持的文件扩展名');
        }

        if ($mimeType === '' || !in_array($mimeType, $rules['mimes'], true)) {
            $allowEmpty = !empty($rules['allow_empty_mime']);
            if (!($allowEmpty && $mimeType === '')) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '不支持的文件 MIME 类型');
            }
        }

        if ($checkSize) {
            $maxSize = (int) env($rules['max_size'], $rules['default_max_size']);
            if ($size <= 0 || $size > $maxSize) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '文件大小超出限制');
            }
        }

        return $extension;
    }

    private function normalizeMime(string $mimeType, string $type): string
    {
        $mimeType = trim($mimeType);
        if ($mimeType !== '') {
            return $mimeType;
        }

        // 浏览器上传 apk/ipa 时常无空 MIME，按类型回落
        return match ($type) {
            'file', 'video', 'document' => 'application/octet-stream',
            default => '',
        };
    }

    private function generateObjectKey(string $type, string $extension): string
    {
        $prefix = self::TYPE_RULES[$type]['prefix'];
        $date = date('Y/m');
        $name = bin2hex(random_bytes(16));

        return "{$prefix}/{$date}/{$name}.{$extension}";
    }

    private function storeLocally(UploadFile $file, string $key, string $filename, int $size): array
    {
        $relative = 'uploads/' . ltrim($key, '/');
        $absolute = public_path() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, '本地上传目录创建失败');
        }

        $source = $file->getPathname();
        if ($source === '' || !is_file($source)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '临时文件不存在');
        }
        if (!@copy($source, $absolute)) {
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, '本地文件保存失败');
        }

        return [
            'url' => $this->localPublicUrl($relative),
            'key' => $key,
            'filename' => $filename,
            'size' => $size,
            'storage' => 'local',
        ];
    }

    private function localPublicUrl(string $relativePath): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
        $request = request();
        if ($request) {
            $proto = (string) ($request->header('x-forwarded-proto') ?: 'http');
            $host = (string) $request->host();
            if ($host !== '') {
                return $proto . '://' . $host . $path;
            }
        }

        return $path;
    }

    private function isS3Configured(): bool
    {
        $config = $this->settingService->getS3Config();

        return $config['bucket'] !== ''
            && $config['credentials_key'] !== ''
            && $config['credentials_secret'] !== '';
    }

    private function publicUrl(string $key): string
    {
        return $this->baseUrl() . '/' . ltrim($key, '/');
    }

    private function baseUrl(): string
    {
        $config = $this->settingService->getS3Config();
        if ($config['url'] !== '') {
            return rtrim($config['url'], '/');
        }

        $bucket = $this->bucket();
        $region = $config['region'];

        return "https://{$bucket}.s3.{$region}.amazonaws.com";
    }

    private function bucket(): string
    {
        $bucket = $this->settingService->getS3Config()['bucket'];
        if ($bucket === '') {
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, 'S3 存储桶未配置');
        }

        return $bucket;
    }

    private function client(): S3Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $config = $this->settingService->getS3Config();
        if ($config['credentials_key'] === '' || $config['credentials_secret'] === '') {
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, 'S3 访问凭证未配置');
        }

        $options = [
            'version' => 'latest',
            'region' => $config['region'] !== '' ? $config['region'] : 'ap-east-1',
            'credentials' => [
                'key' => $config['credentials_key'],
                'secret' => $config['credentials_secret'],
            ],
            'http' => $this->httpOptions($config),
        ];

        $this->client = new S3Client($options);

        return $this->client;
    }

    /** @param array<string, mixed> $config */
    private function httpOptions(array $config): array
    {
        $http = [];

        if (!empty($config['proxy'])) {
            $http['proxy'] = $config['proxy'];
        }

        $caFile = $this->resolveCaBundle();
        if ($caFile !== null) {
            $http['verify'] = $caFile;
        } elseif (filter_var(env('S3_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN) === false) {
            // 仅本地调试：未配置 CA 且显式关闭校验时跳过
            $http['verify'] = false;
        }

        return $http;
    }

    private function resolveCaBundle(): ?string
    {
        $candidates = [
            (string) env('CURL_CA_BUNDLE', ''),
            (string) ini_get('curl.cainfo'),
            (string) ini_get('openssl.cafile'),
            runtime_path('ssl/cacert.pem'),
            'D:/BtSoft/php/82/extras/ssl/cacert.pem',
        ];

        foreach ($candidates as $path) {
            $path = trim(str_replace(["\r", "\n"], '', $path));
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}

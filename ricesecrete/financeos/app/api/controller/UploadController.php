<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\S3Service;
use support\Request;
use Webman\Http\UploadFile;

class UploadController
{
    protected S3Service $s3Service;

    public function __construct(?S3Service $s3Service = null)
    {
        $this->s3Service = $s3Service ?? new S3Service();
    }

    public function store(Request $request)
    {
        $type = trim((string) $request->post('type', 'image'));
        if (!in_array($type, S3Service::ALLOWED_TYPES, true)) {
            return fail(42201, 'type 必须是 image、document、video 或 file');
        }

        $file = $request->file('file');
        if (!$file instanceof UploadFile) {
            return fail(42201, '请选择文件');
        }

        return success($this->s3Service->upload($file, $type));
    }
}

<?php

declare(strict_types=1);

use Webman\Route;

Route::get('/', function () {
    $index = public_path() . '/index.html';
    if (!is_file($index)) {
        return response('H5 not found', 404);
    }

    return response(file_get_contents($index), 200, ['Content-Type' => 'text/html; charset=utf-8']);
});

require __DIR__ . '/routes/admin.php';
require __DIR__ . '/routes/api.php';

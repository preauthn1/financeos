<?php

declare(strict_types=1);

return [
  '@' => [
    app\middleware\CorsMiddleware::class,
    app\middleware\LocaleMiddleware::class,
  ],
];

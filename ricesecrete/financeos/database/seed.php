<?php

declare(strict_types=1);

/**
 * 数据填充脚本
 * 用法: php database/seed.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use database\support\DatabaseBootstrap;

DatabaseBootstrap::init();

$seeder = require __DIR__ . '/seeders/DatabaseSeeder.php';
$seeder->run();

echo "Seed completed.\n";

<?php
return [
    'enable' => true,

    'build_dir'  => BASE_PATH . DIRECTORY_SEPARATOR . 'build',

    'phar_filename' => 'webman.phar',

    'phar_format' => Phar::PHAR,

    'phar_compression' => Phar::NONE,

    'bin_filename' => 'webman.bin',

    'signature_algorithm'=> Phar::SHA256,

    'private_key_file'  => '',

    'exclude_pattern'   => '#^(?!.*(composer.json|/.github/|/.idea/|/.git/|/.setting/|/runtime/|/vendor-bin/|/build/|/vendor/webman/admin/))(.*)$#',

    'exclude_files'     => [
        '.env', 'LICENSE', 'composer.json', 'composer.lock', 'start.php', 'webman.phar', 'webman.bin'
    ],

    'custom_ini' => '
memory_limit = 256M
    ',
];

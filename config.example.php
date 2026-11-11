<?php
// Скопируйте файл в config.php и укажите параметры своей MySQL-базы.
// Не публикуйте config.php с реальными паролями в открытом репозитории.
return [
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('DB_NAME') ?: 'uyutny_bereg',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    'base_url' => getenv('APP_BASE_URL') ?: '',
    'upload_dir' => __DIR__ . '/uploads/avatars',
    'upload_url' => 'uploads/avatars',
    'secure_cookie' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
];
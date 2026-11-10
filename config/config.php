<?php

return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'marketplace_db',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
    'auth' => [
        'secret'        => 'marketplace-secret-key-change-me',
        'token_ttl'     => 86400, // 24 jam
    ],
];

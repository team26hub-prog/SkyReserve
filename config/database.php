<?php

declare(strict_types=1);

$required = static function (string $name): string {
    $value = getenv($name);
    if ($value === false) {
        throw new RuntimeException('Missing database setting: ' . $name . '. Configure .env or server environment variables.');
    }
    return $value;
};

return [
    'host' => $required('DB_HOST'),
    'port' => $required('DB_PORT'),
    'database' => $required('DB_DATABASE'),
    'username' => $required('DB_USERNAME'),
    'password' => $required('DB_PASSWORD'),
    'charset' => 'utf8mb4',
];

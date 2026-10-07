<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Environment
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return; // Hosting environments may supply variables directly.
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read environment configuration.');
        }
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!preg_match('/\A([A-Z_][A-Z0-9_]*)\s*=\s*(.*)\z/', $line, $matches)) {
                throw new RuntimeException('Invalid .env syntax on line ' . ($index + 1));
            }
            [, $name, $value] = $matches;
            if (str_starts_with($value, '"') || str_starts_with($value, "'")) {
                if (strlen($value) < 2 || !str_ends_with($value, $value[0])) {
                    throw new RuntimeException('Unclosed .env quote on line ' . ($index + 1));
                }
                $value = substr($value, 1, -1);
            }
            if (str_contains($value, "\0")) {
                throw new RuntimeException('Invalid .env value on line ' . ($index + 1));
            }
            // Existing process/server settings take precedence, including empty values.
            if (getenv($name) === false) {
                putenv($name . '=' . $value);
            }
        }
    }
}

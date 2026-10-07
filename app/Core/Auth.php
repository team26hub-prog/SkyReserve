<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    private static bool $loaded = false;
    private static ?array $user = null;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['user_id'] ?? null;
            if (is_int($id) && $id > 0) {
                $user = (new User())->findById($id);
                if ($user && $user['status'] === 'active' && in_array($user['role'], ['customer', 'admin'], true)) {
                    self::$user = $user;
                } else {
                    unset($_SESSION['user_id']);
                }
            }
        }
        return self::$user;
    }

    public static function login(array $user): void
    {
        $_SESSION = [];
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Unable to regenerate session.');
        }
        $_SESSION['user_id'] = (int) $user['id'];
        self::$loaded = false;
        self::$user = null;
        Session::csrfToken();
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$loaded = true;
        self::$user = null;
    }

    public static function home(array $user): string
    {
        return $user['role'] === 'admin' ? '/admin' : '/profile';
    }
}

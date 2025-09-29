<?php

namespace App\Security;

use RuntimeException;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function assertValid(string $token): void
    {
        $expected = $_SESSION['csrf_token'] ?? null;
        if (!$expected || !hash_equals($expected, $token)) {
            throw new RuntimeException('Invalid CSRF token');
        }
    }
}

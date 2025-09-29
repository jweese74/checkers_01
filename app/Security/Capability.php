<?php

namespace App\Security;

class Capability
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function verify(?string $expected, ?string $provided): bool
    {
        if (!$expected) {
            return true;
        }
        if (!$provided) {
            return false;
        }
        return hash_equals($expected, $provided);
    }
}

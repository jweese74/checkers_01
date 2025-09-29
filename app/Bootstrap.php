<?php

namespace App;

use App\Storage\Db;

class Bootstrap
{
    public static function init(array $config): array
    {
        date_default_timezone_set('UTC');
        if (!headers_sent()) {
            header_remove('X-Powered-By');
        }

        if ($config['error_display']) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
        }

        if (!is_dir($config['data_dir'])) {
            mkdir($config['data_dir'], 0775, true);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => $config['security']['cookie_secure'],
                'cookie_samesite' => 'Lax',
            ]);
        }

        ob_start();

        self::registerAutoloader();
        self::sendSecurityHeaders($config);

        $db = Db::open($config['database_path']);
        return ['db' => $db];
    }

    private static function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            if (str_starts_with($class, 'App\\')) {
                $path = __DIR__ . '/' . str_replace('App\\', '', $class) . '.php';
                $path = str_replace('\\', DIRECTORY_SEPARATOR, $path);
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
    }

    private static function sendSecurityHeaders(array $config): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: geolocation=()');
        header('X-Frame-Options: SAMEORIGIN');
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['csp_nonce'] = $nonce;
        if ($config['security']['csp_enabled']) {
            $csp = "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data:;";
            header('Content-Security-Policy: ' . $csp);
        }
    }
}

<?php

namespace App\Http;

use App\Security\Csrf;
use InvalidArgumentException;

class Request
{
    private array $get;
    private array $post;
    private array $server;

    public function __construct(array $get, array $post, array $server)
    {
        $this->get = $get;
        $this->post = $post;
        $this->server = $server;
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function getAction(): string
    {
        $action = $this->get['action'] ?? $this->post['action'] ?? 'home';
        $allowed = ['home','view','new','move','state','stream'];
        if (!in_array($action, $allowed, true)) {
            throw new InvalidArgumentException('Unknown action');
        }
        return $action;
    }

    public function getGameId(): ?string
    {
        $id = $this->get['id'] ?? $this->post['id'] ?? null;
        if ($id === null || $id === '') {
            return null;
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{4,64}$/', $id)) {
            throw new InvalidArgumentException('Invalid game id');
        }
        return $id;
    }

    public function getLanguage(array $supported, string $default): string
    {
        $lang = $this->get['lang'] ?? $this->post['lang'] ?? $default;
        return in_array($lang, $supported, true) ? $lang : $default;
    }

    public function getName(string $key): ?string
    {
        $value = trim((string)($this->post[$key] ?? ''));
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > 40) {
            throw new InvalidArgumentException('Name too long');
        }
        return $value;
    }

    public function getMode(): string
    {
        $mode = $this->post['mode'] ?? 'shared';
        $allowed = ['shared', 'hotseat'];
        if (!in_array($mode, $allowed, true)) {
            throw new InvalidArgumentException('Invalid mode');
        }
        return $mode;
    }

    public function getEmail(string $key): ?string
    {
        $value = trim((string)($this->post[$key] ?? ''));
        if ($value === '') {
            return null;
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }
        return $value;
    }

    public function getCoordinate(string $key): int
    {
        $value = $this->post[$key] ?? $this->get[$key] ?? null;
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Invalid coordinate');
        }
        $int = (int)$value;
        if ($int < 0 || $int > 7) {
            throw new InvalidArgumentException('Coordinate out of range');
        }
        return $int;
    }

    public function requireCsrf(): void
    {
        if ($this->method() === 'POST') {
            $token = $this->post['_csrf'] ?? '';
            Csrf::assertValid($token);
        }
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    public function getSince(): ?int
    {
        $value = $this->get['since'] ?? $this->post['since'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!ctype_digit((string)$value)) {
            throw new InvalidArgumentException('Invalid timestamp');
        }
        return (int)$value;
    }

    public function getMove(string $key): array
    {
        $source = $this->post[$key] ?? $this->get[$key] ?? null;
        if ($source === null || $source === '') {
            throw new InvalidArgumentException('Missing move coordinate');
        }
        if (strpos($source, ',') !== false) {
            [$r, $c] = array_map('trim', explode(',', $source));
        } else {
            $r = $this->post[$key . '_r'] ?? $this->get[$key . '_r'] ?? null;
            $c = $this->post[$key . '_c'] ?? $this->get[$key . '_c'] ?? null;
        }
        if ($r === null || $c === null) {
            throw new InvalidArgumentException('Invalid move coordinate');
        }
        if (!ctype_digit((string)$r) || !ctype_digit((string)$c)) {
            throw new InvalidArgumentException('Invalid move coordinate');
        }
        $ri = (int)$r;
        $ci = (int)$c;
        if ($ri < 0 || $ri > 7 || $ci < 0 || $ci > 7) {
            throw new InvalidArgumentException('Move coordinate out of range');
        }
        return [$ri, $ci];
    }

    public function getCapability(): ?string
    {
        $cap = $this->get['cap'] ?? $this->post['cap'] ?? ($_COOKIE['cap'] ?? null);
        if ($cap === null || $cap === '') {
            return null;
        }
        if (!preg_match('/^[A-Fa-f0-9]{16,64}$/', $cap)) {
            throw new InvalidArgumentException('Invalid capability token');
        }
        return $cap;
    }
}

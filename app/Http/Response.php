<?php

namespace App\Http;

class Response
{
    private int $status;
    private array $headers = [];
    private string $body;
    private $streamer;

    public function __construct(int $status = 200, string $body = '')
    {
        $this->status = $status;
        $this->body = $body;
    }

    public function setHeader(string $header, string $value): self
    {
        $this->headers[$header] = $value;
        return $this;
    }

    public static function json(array $data, int $status = 200): self
    {
        $response = new self($status, json_encode($data, JSON_UNESCAPED_UNICODE));
        $response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $response;
    }

    public static function html(string $body, int $status = 200): self
    {
        $response = new self($status, $body);
        $response->setHeader('Content-Type', 'text/html; charset=utf-8');
        return $response;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        $response = new self($status);
        $response->setHeader('Location', $url);
        return $response;
    }

    public static function empty(int $status): self
    {
        return new self($status, '');
    }

    public static function sse(callable $callback): self
    {
        $response = new self(200);
        $response->streamer = $callback;
        $response->setHeader('Content-Type', 'text/event-stream');
        $response->setHeader('Cache-Control', 'no-cache');
        $response->setHeader('Connection', 'keep-alive');
        return $response;
    }

    public function withEtag(string $etag, ?string $ifNoneMatch = null): self
    {
        $this->setHeader('ETag', '"' . $etag . '"');
        if ($ifNoneMatch !== null && trim($ifNoneMatch, '"') === $etag) {
            $this->status = 304;
            $this->body = '';
        }
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $header => $value) {
            header($header . ': ' . $value, true);
        }
        if ($this->streamer) {
            ($this->streamer)($this);
            return;
        }
        echo $this->body;
    }

    public function writeEvent(string $event, array $payload, ?int $retryMs = null): void
    {
        if ($retryMs !== null) {
            echo 'retry: ' . $retryMs . "\n";
        }
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
        @ob_flush();
        @flush();
    }
}

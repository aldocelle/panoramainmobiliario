<?php
declare(strict_types=1);
namespace Panorama;
final class Response
{
    public function __construct(
        public mixed $data = null,
        public int $status = 200,
        public array $headers = [],
        public ?array $cookie = null,
        public ?string $raw = null,
    ) {}
    public function send(): void
    {
        http_response_code($this->status);
        header(
            "Content-Type: " .
                ($this->raw !== null
                    ? "text/csv; charset=utf-8"
                    : "application/json; charset=utf-8"),
        );
        foreach ($this->headers as $k => $v) {
            header($k . ": " . $v);
        }
        if ($this->cookie) {
            setcookie(
                $this->cookie["name"],
                $this->cookie["value"],
                $this->cookie["options"],
            );
        }
        if ($this->status !== 204) {
            echo $this->raw ?? Support::json($this->data);
        }
    }
}

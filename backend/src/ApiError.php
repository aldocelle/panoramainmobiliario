<?php
declare(strict_types=1);
namespace Panorama;
final class ApiError extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
    public static function require(
        bool $condition,
        int $status,
        string $code,
        string $message,
    ): void {
        if (!$condition) {
            throw new self($status, $code, $message);
        }
    }
}

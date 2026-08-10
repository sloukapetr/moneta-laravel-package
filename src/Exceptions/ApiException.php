<?php

namespace SloukaPetr\Moneta\Exceptions;

class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $statusCode = 0,
        public readonly ?string $errorCode = null,
        public readonly ?string $scope = null,
        public readonly array $parameters = [],
        public readonly array $payload = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->getCode();
    }
}

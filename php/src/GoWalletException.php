<?php

namespace GoWallet;

class GoWalletException extends \RuntimeException
{
    /** @var int */
    private $statusCode;

    /** @var array|null */
    private $body;

    public function __construct(int $statusCode, ?array $body = null)
    {
        $message = $body['error'] ?? "HTTP {$statusCode}";
        parent::__construct($message, $statusCode);

        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): ?array
    {
        return $this->body;
    }
}

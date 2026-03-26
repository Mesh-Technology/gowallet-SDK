<?php

namespace GoWallet;

class Client
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiSecret;

    /** @var int */
    private $timeout;

    /**
     * @param array $config {
     *     @type string $base_url   Base URL of the GoWallet API (required)
     *     @type string $api_key    HMAC API key (required)
     *     @type string $api_secret HMAC API secret (required)
     *     @type int    $timeout    Request timeout in seconds (default: 30)
     * }
     */
    public function __construct(array $config)
    {
        if (empty($config['base_url'])) {
            throw new \InvalidArgumentException('base_url is required');
        }
        if (empty($config['api_key'])) {
            throw new \InvalidArgumentException('api_key is required');
        }
        if (empty($config['api_secret'])) {
            throw new \InvalidArgumentException('api_secret is required');
        }

        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->apiKey = $config['api_key'];
        $this->apiSecret = $config['api_secret'];
        $this->timeout = $config['timeout'] ?? 30;
    }

    // ── Wallet ──

    /**
     * Generate or retrieve a deposit wallet for a user on a network.
     *
     * @param string $userId  The user identifier
     * @param string $network Network name (TRON, BSC, ETHEREUM, SOLANA, etc.)
     * @return array Wallet response {user_id, address, network, created_at}
     */
    public function createWallet(string $userId, string $network): array
    {
        return $this->post('/api/v1/wallet', [
            'userId' => $userId,
            'network' => $network,
        ]);
    }

    // ── Public (no auth) ──

    /**
     * Health check.
     *
     * @return array {status: string}
     */
    public function health(): array
    {
        return $this->request('GET', '/health', null, false);
    }

    // ── IPN Verification ──

    /**
     * Verify the HMAC signature of an incoming IPN webhook payload.
     *
     * @param array $payload The full IPN payload including signature
     * @return bool True if the signature is valid
     */
    public function verifyIPN(array $payload): bool
    {
        return HMAC::verifyIPNSignature($payload, $this->apiSecret);
    }

    // ── Internal HTTP ──

    /**
     * @return array
     */
    private function get(string $path): array
    {
        return $this->request('GET', $path, null, true);
    }

    /**
     * @return array
     */
    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body, true);
    }

    /**
     * @param string     $method HTTP method
     * @param string     $path   API path
     * @param array|null $body   Request body (null for GET/DELETE)
     * @param bool       $auth   Whether to include HMAC auth headers
     * @return array Decoded JSON response
     * @throws GoWalletException
     */
    private function request(string $method, string $path, ?array $body, bool $auth): array
    {
        $url = $this->baseUrl . $path;

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $payload = '';
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($auth) {
            $signature = HMAC::signPayload($body ?? '', $this->apiSecret);
            $headers[] = 'HMAC_KEY: ' . $this->apiKey;
            $headers[] = 'HMAC_SIGN: ' . $signature;
            $headers[] = 'X-Timestamp: ' . time();
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($payload !== '' && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new GoWalletException(0, ['error' => 'cURL error: ' . $curlError]);
        }

        $decoded = json_decode($response, true);

        if ($statusCode >= 200 && $statusCode < 300) {
            return is_array($decoded) ? $decoded : ['raw' => $response];
        }

        throw new GoWalletException($statusCode, is_array($decoded) ? $decoded : ['error' => $response]);
    }
}

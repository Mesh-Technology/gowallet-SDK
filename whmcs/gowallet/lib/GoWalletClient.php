<?php
/**
 * Lightweight HTTP client for the GoWallet API.
 *
 * @package GoWallet
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

class GoWalletClient
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiSecret;

    /** @var int */
    private $timeout;

    public function __construct(string $baseUrl, string $apiKey, string $apiSecret, int $timeout = 30)
    {
        $this->baseUrl   = rtrim($baseUrl, '/');
        $this->apiKey    = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->timeout   = $timeout;
    }

    /**
     * Create or retrieve a deposit wallet.
     *
     * @param string $userId  Unique user/invoice identifier.
     * @param string $network Blockchain network (TRON, BSC, ETHEREUM, SOLANA).
     * @return array{user_id: string, address: string, network: string, created_at: string}
     * @throws Exception
     */
    public function createWallet(string $userId, string $network): array
    {
        return $this->post('/api/v1/wallet', [
            'userId'  => $userId,
            'network' => $network,
        ]);
    }

    /**
     * Health check (no authentication).
     *
     * @return array{status: string}
     * @throws Exception
     */
    public function health(): array
    {
        return $this->request('GET', '/health', null, false);
    }

    /**
     * Get all active networks and their tokens (no authentication).
     *
     * @return array{networks: array, count: int}
     * @throws Exception
     */
    public function getNetworks(): array
    {
        return $this->request('GET', '/api/v1/public/networks', null, false);
    }

    // -----------------------------------------------------------------
    //  Private helpers
    // -----------------------------------------------------------------

    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body, true);
    }

    /**
     * @throws Exception
     */
    private function request(string $method, string $path, ?array $body, bool $auth): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = ['Content-Type: application/json'];
        $jsonBody = '';

        if ($body !== null) {
            $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        if ($auth) {
            $timestamp = (string) time();
            $signature = GoWalletHMAC::signPayload($jsonBody ?: '', $this->apiSecret);

            $headers[] = 'HMAC_KEY: ' . $this->apiKey;
            $headers[] = 'HMAC_SIGN: ' . $signature;
            $headers[] = 'X-Timestamp: ' . $timestamp;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('GoWallet API request failed: ' . $curlError);
        }

        $data = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $data['error'] ?? 'Unknown error';
            throw new Exception("GoWallet API error ($httpCode): $message");
        }

        return $data ?? [];
    }
}

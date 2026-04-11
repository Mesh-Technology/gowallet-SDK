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

    // ── Invoices ──

    /**
     * Create a new payment invoice.
     *
     * @param float  $priceAmount   Invoice amount in fiat
     * @param string $priceCurrency Fiat currency code (USD, EUR, etc.)
     * @param array  $options       Optional: order_id, title, description, callback_url, success_url, cancel_url
     * @return array Invoice data
     */
    public function createInvoice(float $priceAmount, string $priceCurrency, array $options = []): array
    {
        $body = array_merge([
            'price_amount' => $priceAmount,
            'price_currency' => $priceCurrency,
        ], array_filter($options));

        return $this->post('/api/v1/invoices', $body);
    }

    /**
     * Get invoice detail by UUID.
     *
     * @param string $invoiceId The invoice UUID
     * @return array Invoice detail with payments and status history
     */
    public function getInvoice(string $invoiceId): array
    {
        return $this->get('/api/v1/invoices/' . $invoiceId);
    }

    /**
     * List invoices with optional filters.
     *
     * @param array $params Optional filters: page, limit, status, network, order_id
     * @return array Paginated invoice list
     */
    public function listInvoices(array $params = []): array
    {
        $query = http_build_query(array_filter($params, fn($v) => $v !== null && $v !== ''));
        $path = '/api/v1/invoices';
        if ($query) {
            $path .= '?' . $query;
        }
        return $this->get($path);
    }

    /**
     * Select payment currency and network for an invoice (new -> pending).
     *
     * @param string $invoiceId   The invoice UUID
     * @param string $payCurrency Crypto currency code (BTC, ETH, USDT, etc.)
     * @param string $payNetwork  Network name (TRON, BSC, ETHEREUM, SOLANA)
     * @return array Updated invoice data
     */
    public function selectPayCurrency(string $invoiceId, string $payCurrency, string $payNetwork): array
    {
        return $this->post('/api/v1/invoices/' . $invoiceId . '/select', [
            'pay_currency' => $payCurrency,
            'pay_network' => $payNetwork,
        ]);
    }

    /**
     * Cancel an invoice (only from new/pending status).
     *
     * @param string $invoiceId The invoice UUID
     * @return array Updated invoice data
     */
    public function cancelInvoice(string $invoiceId): array
    {
        return $this->post('/api/v1/invoices/' . $invoiceId . '/cancel', []);
    }

    /**
     * Verify the HMAC-SHA512 signature of an invoice callback payload.
     *
     * @param array  $payload   The callback payload
     * @param string $signature The signature from the X-GoWallet-Signature header
     * @return bool True if valid
     */
    public function verifyInvoiceCallback(array $payload, string $signature): bool
    {
        $expected = HMAC::signPayload($payload, $this->apiSecret);
        return hash_equals($expected, $signature);
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

    /**
     * Get all active networks and their tokens (no auth required).
     *
     * @return array {networks: array, count: int}
     */
    public function getNetworks(): array
    {
        return $this->request('GET', '/api/v1/public/networks', null, false);
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

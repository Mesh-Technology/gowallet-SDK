<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lightweight HTTP client for the GoWallet API.
 */
class GoWallet_API_Client
{
    private string $base_url;
    private string $api_key;
    private string $api_secret;
    private int    $timeout;

    public function __construct(string $base_url, string $api_key, string $api_secret, int $timeout = 30)
    {
        $this->base_url   = rtrim($base_url, '/');
        $this->api_key    = $api_key;
        $this->api_secret = $api_secret;
        $this->timeout    = $timeout;
    }

    /**
     * Generate or retrieve a deposit wallet for a user on a given network.
     *
     * @param string $user_id  Unique user identifier.
     * @param string $network  Blockchain network (TRON, BSC, ETHEREUM, SOLANA).
     * @return array{user_id: string, address: string, network: string, created_at: string}
     * @throws Exception On API error.
     */
    public function create_wallet(string $user_id, string $network): array
    {
        return $this->post('/api/v1/wallet', [
            'userId'  => $user_id,
            'network' => $network,
        ]);
    }

    /**
     * Health check — no authentication required.
     *
     * @return array{status: string}
     */
    public function health(): array
    {
        return $this->get('/health');
    }

    /**
     * Get all active networks and their tokens — no authentication required.
     *
     * @return array{networks: array, count: int}
     */
    public function get_networks(): array
    {
        return $this->get('/api/v1/public/networks');
    }

    // -----------------------------------------------------------------
    //  Private helpers
    // -----------------------------------------------------------------

    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body, true);
    }

    private function get(string $path): array
    {
        return $this->request('GET', $path, null, false);
    }

    private function request(string $method, string $path, ?array $body, bool $auth): array
    {
        $url = $this->base_url . $path;

        $args = [
            'method'  => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];

        $json_body = '';
        if ($body !== null) {
            $json_body    = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $args['body'] = $json_body;
        }

        if ($auth) {
            $timestamp = (string) time();
            $signature = GoWallet_HMAC::sign_payload($json_body ?: '', $this->api_secret);

            $args['headers']['HMAC_KEY']    = $this->api_key;
            $args['headers']['HMAC_SIGN']   = $signature;
            $args['headers']['X-Timestamp'] = $timestamp;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('GoWallet API request failed: ' . $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $data   = json_decode(wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300) {
            $message = $data['error'] ?? 'Unknown error';
            throw new Exception("GoWallet API error ($status): $message");
        }

        return $data ?? [];
    }
}

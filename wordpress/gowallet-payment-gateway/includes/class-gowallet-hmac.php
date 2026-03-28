<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HMAC-SHA512 signing and IPN signature verification.
 */
class GoWallet_HMAC
{
    /**
     * Sign a JSON payload with HMAC-SHA512.
     *
     * @param mixed  $payload The data to sign (will be JSON-encoded compactly).
     * @param string $secret  API secret.
     * @return string Hex-encoded signature.
     */
    public static function sign_payload($payload, string $secret): string
    {
        $body = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash_hmac('sha512', $body, $secret);
    }

    /**
     * Verify an IPN webhook signature using timing-safe comparison.
     *
     * @param array  $payload Decoded JSON body from the IPN request (must include 'signature').
     * @param string $secret  API secret.
     * @return bool
     */
    public static function verify_ipn(array $payload, string $secret): bool
    {
        if (empty($payload['signature'])) {
            return false;
        }

        $signature = $payload['signature'];
        $data      = $payload;
        unset($data['signature']);

        $expected = self::sign_payload($data, $secret);

        return hash_equals($expected, $signature);
    }
}

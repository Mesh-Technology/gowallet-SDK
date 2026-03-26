<?php

namespace GoWallet;

class HMAC
{
    /**
     * Generate HMAC-SHA512 signature for a request payload.
     *
     * @param mixed  $payload The request body (array/object for POST, empty string for GET/DELETE)
     * @param string $secret  The API secret key
     * @return string Hex-encoded HMAC-SHA512 signature
     */
    public static function signPayload($payload, string $secret): string
    {
        $message = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash_hmac('sha512', $message, $secret);
    }

    /**
     * Verify an IPN webhook signature (constant-time comparison).
     *
     * @param array  $payload The full IPN payload including signature
     * @param string $secret  Your API secret
     * @return bool True if the signature is valid
     */
    public static function verifyIPNSignature(array $payload, string $secret): bool
    {
        if (!isset($payload['signature']) || !is_string($payload['signature'])) {
            return false;
        }

        $received = $payload['signature'];

        // Build message from all fields except signature
        $data = $payload;
        unset($data['signature']);
        $message = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $expected = hash_hmac('sha512', $message, $secret);

        return hash_equals($expected, $received);
    }
}

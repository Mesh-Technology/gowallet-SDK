<?php
/**
 * HMAC-SHA512 signing and IPN signature verification for GoWallet.
 *
 * @package GoWallet
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

class GoWalletHMAC
{
    /**
     * Sign a JSON payload with HMAC-SHA512.
     *
     * @param mixed  $payload Data to sign (will be JSON-encoded compactly if not a string).
     * @param string $secret  API secret.
     * @return string Hex-encoded signature.
     */
    public static function signPayload($payload, string $secret): string
    {
        $body = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash_hmac('sha512', $body, $secret);
    }

    /**
     * Verify an IPN webhook signature.
     *
     * @param array  $payload Decoded JSON body (must include 'signature').
     * @param string $secret  API secret.
     * @return bool
     */
    public static function verifyIPN(array $payload, string $secret): bool
    {
        if (empty($payload['signature'])) {
            return false;
        }

        $signature = $payload['signature'];
        $data = $payload;
        unset($data['signature']);

        $expected = self::signPayload($data, $secret);

        return hash_equals($expected, $signature);
    }
}

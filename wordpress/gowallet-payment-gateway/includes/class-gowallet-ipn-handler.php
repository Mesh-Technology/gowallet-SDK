<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles incoming IPN (Instant Payment Notification) webhooks from GoWallet.
 *
 * Register the endpoint via the WordPress REST API.
 */
class GoWallet_IPN_Handler
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    /**
     * Register the IPN callback endpoint: POST /wp-json/gowallet/v1/ipn
     */
    public function register_route(): void
    {
        register_rest_route('gowallet/v1', '/ipn', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_ipn'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Process an incoming IPN notification.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_ipn(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $request->get_json_params();

        if (empty($payload)) {
            return new WP_REST_Response(['error' => 'Invalid payload'], 400);
        }

        // Load gateway settings to obtain api_secret
        $settings   = get_option('woocommerce_gowallet_settings', []);
        $api_secret = $settings['api_secret'] ?? '';

        if (empty($api_secret)) {
            return new WP_REST_Response(['error' => 'Gateway not configured'], 500);
        }

        // Verify HMAC signature
        if (!GoWallet_HMAC::verify_ipn($payload, $api_secret)) {
            return new WP_REST_Response(['error' => 'Invalid signature'], 403);
        }

        $user_id = sanitize_text_field($payload['user_id'] ?? '');
        $amount  = floatval($payload['amount'] ?? 0);
        $token   = sanitize_text_field($payload['token'] ?? '');
        $network = sanitize_text_field($payload['network'] ?? '');
        $tx_id   = sanitize_text_field($payload['transaction_id'] ?? '');

        if (empty($user_id) || $amount <= 0) {
            return new WP_REST_Response(['error' => 'Missing required fields'], 400);
        }

        // user_id format: "woo-{order_id}"
        if (strpos($user_id, 'woo-') !== 0) {
            return new WP_REST_Response(['error' => 'Unknown user_id format'], 400);
        }

        $order_id = absint(substr($user_id, 4));
        $order    = wc_get_order($order_id);

        if (!$order) {
            return new WP_REST_Response(['error' => 'Order not found'], 404);
        }

        // Avoid processing the same transaction twice
        $processed_tx = $order->get_meta('_gowallet_tx_id');
        if ($processed_tx === $tx_id) {
            return new WP_REST_Response(['status' => 'already_processed'], 200);
        }

        // Record transaction metadata
        $order->update_meta_data('_gowallet_tx_id', $tx_id);
        $order->update_meta_data('_gowallet_token', $token);
        $order->update_meta_data('_gowallet_amount', $amount);
        $order->update_meta_data('_gowallet_network', $network);

        // Determine resulting order status from settings
        $target_status = $settings['order_status_on_payment'] ?? 'processing';

        $order->add_order_note(sprintf(
            /* translators: 1: amount, 2: token, 3: network, 4: transaction id */
            __('GoWallet payment received: %1$s %2$s on %3$s (TX: %4$s)', 'gowallet-payment-gateway'),
            $amount,
            $token,
            $network,
            $tx_id
        ));

        $order->payment_complete($tx_id);
        $order->update_status($target_status);
        $order->save();

        return new WP_REST_Response(['status' => 'ok'], 200);
    }
}

// Bootstrap the IPN handler.
new GoWallet_IPN_Handler();

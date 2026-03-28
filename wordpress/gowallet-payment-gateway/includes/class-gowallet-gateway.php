<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce payment gateway for GoWallet cryptocurrency payments.
 */
class WC_GoWallet_Gateway extends WC_Payment_Gateway
{
    /** @var string */
    private $api_url;

    /** @var string */
    private $api_key;

    /** @var string */
    private $api_secret;

    /** @var string */
    private $network;

    public function __construct()
    {
        $this->id                 = 'gowallet';
        $this->icon               = GOWALLET_PLUGIN_URL . 'assets/icon.png';
        $this->has_fields         = false;
        $this->method_title       = __('GoWallet', 'gowallet-payment-gateway');
        $this->method_description = __('Accept cryptocurrency payments (USDT, USDC, etc.) via GoWallet on TRON, BSC, Ethereum, and Solana networks.', 'gowallet-payment-gateway');

        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled     = $this->get_option('enabled');
        $this->api_url     = $this->get_option('api_url');
        $this->api_key     = $this->get_option('api_key');
        $this->api_secret  = $this->get_option('api_secret');
        $this->network     = $this->get_option('network');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * Admin settings form fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'gowallet-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable GoWallet Payments', 'gowallet-payment-gateway'),
                'default' => 'no',
            ],
            'title' => [
                'title'       => __('Title', 'gowallet-payment-gateway'),
                'type'        => 'text',
                'description' => __('Payment method title shown to customers at checkout.', 'gowallet-payment-gateway'),
                'default'     => __('Crypto Payment (USDT)', 'gowallet-payment-gateway'),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __('Description', 'gowallet-payment-gateway'),
                'type'        => 'textarea',
                'description' => __('Payment method description shown to customers at checkout.', 'gowallet-payment-gateway'),
                'default'     => __('Pay with cryptocurrency (USDT, USDC, etc.) on TRON, BSC, Ethereum, or Solana.', 'gowallet-payment-gateway'),
            ],
            'api_url' => [
                'title'       => __('API URL', 'gowallet-payment-gateway'),
                'type'        => 'text',
                'description' => __('GoWallet API base URL (e.g. https://api.example.com).', 'gowallet-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'api_key' => [
                'title'       => __('API Key', 'gowallet-payment-gateway'),
                'type'        => 'text',
                'description' => __('Your GoWallet API key (HMAC key).', 'gowallet-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'api_secret' => [
                'title'       => __('API Secret', 'gowallet-payment-gateway'),
                'type'        => 'password',
                'description' => __('Your GoWallet API secret.', 'gowallet-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'network' => [
                'title'       => __('Network', 'gowallet-payment-gateway'),
                'type'        => 'select',
                'description' => __('Default blockchain network for deposit wallets. Networks are fetched from the GoWallet API when you save settings.', 'gowallet-payment-gateway'),
                'default'     => 'TRON',
                'options'     => $this->get_network_options(),
            ],
            'order_status_on_payment' => [
                'title'       => __('Order Status After Payment', 'gowallet-payment-gateway'),
                'type'        => 'select',
                'description' => __('WooCommerce order status set when IPN confirms payment.', 'gowallet-payment-gateway'),
                'default'     => 'processing',
                'options'     => [
                    'processing' => __('Processing', 'gowallet-payment-gateway'),
                    'completed'  => __('Completed', 'gowallet-payment-gateway'),
                ],
            ],
        ];
    }

    /**
     * Fetch available networks from the GoWallet API for the admin dropdown.
     *
     * Falls back to a static list if the API URL is not yet configured or the request fails.
     *
     * @return array<string, string>
     */
    private function get_network_options(): array
    {
        $api_url = $this->get_option('api_url');

        if (empty($api_url)) {
            return $this->default_network_options();
        }

        $cached = get_transient('gowallet_networks');
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        try {
            $client   = new GoWallet_API_Client($api_url, $this->get_option('api_key', ''), $this->get_option('api_secret', ''));
            $response = $client->get_networks();
            $options  = [];

            foreach ($response['networks'] ?? [] as $net) {
                $name   = $net['name'] ?? '';
                $tokens = array_column($net['tokens'] ?? [], 'symbol');
                $label  = $name;
                if (!empty($tokens)) {
                    $label .= ' (' . implode(', ', $tokens) . ')';
                }
                $options[$name] = $label;
            }

            if (!empty($options)) {
                set_transient('gowallet_networks', $options, 300); // cache 5 min
                return $options;
            }
        } catch (Exception $e) {
            // Silently fall back to defaults
        }

        return $this->default_network_options();
    }

    /**
     * Fallback network list when the API is unreachable.
     *
     * @return array<string, string>
     */
    private function default_network_options(): array
    {
        return [
            'TRON'     => 'TRON',
            'BSC'      => 'BSC',
            'ETHEREUM' => 'Ethereum',
            'SOLANA'   => 'Solana',
        ];
    }

    /**
     * Process the payment: create a deposit wallet and redirect to the thank-you page.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        try {
            $client = new GoWallet_API_Client(
                $this->api_url,
                $this->api_key,
                $this->api_secret
            );

            $user_id = 'woo-' . $order->get_id();
            $wallet  = $client->create_wallet($user_id, $this->network);

            $order->update_meta_data('_gowallet_address', sanitize_text_field($wallet['address']));
            $order->update_meta_data('_gowallet_network', sanitize_text_field($wallet['network']));
            $order->update_meta_data('_gowallet_user_id', sanitize_text_field($wallet['user_id']));
            $order->update_status('on-hold', __('Awaiting crypto payment via GoWallet.', 'gowallet-payment-gateway'));
            $order->save();

            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();

            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        } catch (Exception $e) {
            wc_add_notice(__('Payment error: ', 'gowallet-payment-gateway') . $e->getMessage(), 'error');
            return ['result' => 'fail'];
        }
    }

    /**
     * Display deposit wallet address on the thank-you page and order details.
     */
    public function thankyou_page_instructions($order_id)
    {
        $order   = wc_get_order($order_id);
        $address = $order->get_meta('_gowallet_address');
        $network = $order->get_meta('_gowallet_network');
        $total   = $order->get_total();

        if (!$address) {
            return;
        }

        echo '<h2>' . esc_html__('Crypto Payment Details', 'gowallet-payment-gateway') . '</h2>';
        echo '<p>' . esc_html__('Please send the exact amount to the address below.', 'gowallet-payment-gateway') . '</p>';
        echo '<table class="woocommerce-table">';
        echo '<tr><th>' . esc_html__('Network', 'gowallet-payment-gateway') . '</th><td>' . esc_html($network) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Deposit Address', 'gowallet-payment-gateway') . '</th><td><code>' . esc_html($address) . '</code></td></tr>';
        echo '<tr><th>' . esc_html__('Amount', 'gowallet-payment-gateway') . '</th><td>' . esc_html($total) . ' ' . esc_html($order->get_currency()) . '</td></tr>';
        echo '</table>';
    }
}

// Hook the thank-you page instructions.
add_action('woocommerce_thankyou_gowallet', [new WC_GoWallet_Gateway(), 'thankyou_page_instructions']);

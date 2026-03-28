<?php
/**
 * Plugin Name: GoWallet Payment Gateway
 * Plugin URI: https://github.com/Mesh-Technology/gowallet-SDK
 * Description: Accept cryptocurrency payments (USDT, USDC, etc.) on TRON, BSC, Ethereum, and Solana via GoWallet.
 * Version: 1.0.0
 * Author: Mesh Technology
 * Author URI: https://github.com/Mesh-Technology
 * License: MIT
 * Text Domain: gowallet-payment-gateway
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GOWALLET_VERSION', '1.0.0');
define('GOWALLET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GOWALLET_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active before initialising.
 */
add_action('plugins_loaded', 'gowallet_init_gateway');

function gowallet_init_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>GoWallet Payment Gateway</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }

    require_once GOWALLET_PLUGIN_DIR . 'includes/class-gowallet-hmac.php';
    require_once GOWALLET_PLUGIN_DIR . 'includes/class-gowallet-api-client.php';
    require_once GOWALLET_PLUGIN_DIR . 'includes/class-gowallet-gateway.php';
    require_once GOWALLET_PLUGIN_DIR . 'includes/class-gowallet-ipn-handler.php';

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_GoWallet_Gateway';
        return $gateways;
    });
}

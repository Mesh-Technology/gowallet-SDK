<?php
/**
 * GoWallet Payment Gateway Module for WHMCS
 *
 * Accepts cryptocurrency payments (USDT, USDC, etc.) on TRON, BSC, Ethereum,
 * and Solana via GoWallet.
 *
 * @package    GoWallet
 * @version    1.0.0
 * @author     Mesh Technology
 * @link       https://github.com/Mesh-Technology/gowallet-SDK
 * @license    MIT
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

require_once __DIR__ . '/lib/GoWalletHMAC.php';
require_once __DIR__ . '/lib/GoWalletClient.php';

/**
 * Fetch available networks from the GoWallet API for the admin dropdown.
 * Falls back to a static list if the API is unreachable.
 *
 * @return string Comma-separated network names for WHMCS dropdown.
 */
function gowallet_fetch_network_options(): string
{
    $gatewayParams = [];
    try {
        $gatewayParams = getGatewayVariables('gowallet');
    } catch (\Throwable $e) {
        // Module not yet activated
    }

    $apiUrl = $gatewayParams['apiUrl'] ?? '';
    if (empty($apiUrl)) {
        return 'TRON,BSC,ETHEREUM,SOLANA';
    }

    try {
        $client   = new GoWalletClient($apiUrl, '', '');
        $response = $client->getNetworks();
        $names    = [];
        foreach ($response['networks'] ?? [] as $net) {
            if (!empty($net['name'])) {
                $names[] = $net['name'];
            }
        }
        if (!empty($names)) {
            return implode(',', $names);
        }
    } catch (\Exception $e) {
        // Fall back to defaults
    }

    return 'TRON,BSC,ETHEREUM,SOLANA';
}

/**
 * Module metadata.
 */
function gowallet_MetaData(): array
{
    return [
        'DisplayName' => 'GoWallet Crypto Payment',
        'APIVersion'  => '1.1',
    ];
}

/**
 * Gateway configuration fields shown in WHMCS admin.
 */
function gowallet_config(): array
{
    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'GoWallet Crypto Payment',
        ],
        'apiUrl' => [
            'FriendlyName' => 'API URL',
            'Type'         => 'text',
            'Size'         => 60,
            'Default'      => '',
            'Description'  => 'GoWallet API base URL (e.g. https://api.example.com)',
        ],
        'apiKey' => [
            'FriendlyName' => 'API Key',
            'Type'         => 'text',
            'Size'         => 60,
            'Default'      => '',
            'Description'  => 'Your HMAC API key.',
        ],
        'apiSecret' => [
            'FriendlyName' => 'API Secret',
            'Type'         => 'password',
            'Size'         => 60,
            'Default'      => '',
            'Description'  => 'Your HMAC API secret.',
        ],
        'network' => [
            'FriendlyName' => 'Default Network',
            'Type'         => 'dropdown',
            'Options'      => gowallet_fetch_network_options(),
            'Default'      => 'TRON',
            'Description'  => 'Blockchain network for deposit wallets (fetched from GoWallet API).',
        ],
    ];
}

/**
 * Generate the payment HTML shown on the WHMCS invoice page.
 *
 * Calls the GoWallet API to create/retrieve a deposit wallet, then displays
 * the deposit address and amount to the customer.
 *
 * @param array $params WHMCS gateway params
 * @return string HTML output
 */
function gowallet_link(array $params): string
{
    $apiUrl    = $params['apiUrl'];
    $apiKey    = $params['apiKey'];
    $apiSecret = $params['apiSecret'];
    $network   = $params['network'];

    $invoiceId = $params['invoiceid'];
    $amount    = $params['amount'];
    $currency  = $params['currency'];

    $userId = 'whmcs-' . $invoiceId;

    try {
        $client = new GoWalletClient($apiUrl, $apiKey, $apiSecret);
        $wallet = $client->createWallet($userId, $network);

        $address     = htmlspecialchars($wallet['address'], ENT_QUOTES, 'UTF-8');
        $networkName = htmlspecialchars($wallet['network'], ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="border:1px solid #ddd;padding:20px;border-radius:8px;max-width:480px;font-family:sans-serif">
    <h3 style="margin-top:0">Pay with Crypto via GoWallet</h3>
    <table style="width:100%;border-collapse:collapse">
        <tr>
            <td style="padding:8px 0;font-weight:bold">Network</td>
            <td style="padding:8px 0">{$networkName}</td>
        </tr>
        <tr>
            <td style="padding:8px 0;font-weight:bold">Deposit Address</td>
            <td style="padding:8px 0;word-break:break-all"><code>{$address}</code></td>
        </tr>
        <tr>
            <td style="padding:8px 0;font-weight:bold">Amount</td>
            <td style="padding:8px 0">{$amount} {$currency}</td>
        </tr>
    </table>
    <p style="margin-top:12px;color:#666;font-size:13px">
        Send the exact amount shown above to the deposit address. Your invoice will be
        marked as paid automatically once the transaction is confirmed on-chain.
    </p>
</div>
HTML;
    } catch (Exception $e) {
        logActivity('GoWallet: Failed to create wallet for invoice #' . $invoiceId . ' — ' . $e->getMessage());
        return '<div style="color:red;padding:10px">Unable to generate payment address. Please contact support.</div>';
    }
}

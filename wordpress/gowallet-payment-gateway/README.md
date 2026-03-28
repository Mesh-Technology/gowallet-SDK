# GoWallet Payment Gateway for WordPress / WooCommerce

Accept cryptocurrency payments (USDT, USDC, DAI, etc.) on TRON, BSC, Ethereum, and Solana directly in your WooCommerce store — powered by **GoWallet**.

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- A GoWallet account with API credentials (API Key & Secret)

## Installation

1. Download the `gowallet-payment-gateway` folder (or the release `.zip`).
2. Upload it to `wp-content/plugins/` on your WordPress site.
3. Activate the plugin from **Plugins → Installed Plugins**.
4. Go to **WooCommerce → Settings → Payments → GoWallet** and configure:
   - **API URL** — your GoWallet API base URL
   - **API Key** — your HMAC API key
   - **API Secret** — your HMAC API secret
   - **Network** — default blockchain network (TRON, BSC, Ethereum, Solana)

## IPN (Webhook) Setup

Set your IPN callback URL in the GoWallet dashboard to:

```
https://yoursite.com/wp-json/gowallet/v1/ipn
```

When a deposit is confirmed on-chain, GoWallet sends a signed `POST` request to this endpoint. The plugin verifies the HMAC-SHA512 signature, matches the payment to the WooCommerce order, and updates the order status automatically.

## How It Works

1. Customer selects **Crypto Payment (USDT)** at checkout.
2. The plugin calls the GoWallet API to generate a unique deposit wallet for the order.
3. The deposit address and network are displayed on the thank-you page.
4. Customer sends the cryptocurrency to the address.
5. GoWallet detects the on-chain deposit and sends an IPN webhook.
6. The plugin verifies the signature and marks the order as **Processing** (or **Completed**).

## Configuration Options

| Setting | Description |
|---------|-------------|
| Enable/Disable | Toggle the payment method on/off |
| Title | Payment method label at checkout |
| Description | Text shown to customers |
| API URL | GoWallet API base URL |
| API Key | Your HMAC API key |
| API Secret | Your HMAC API secret |
| Network | Default blockchain network |
| Order Status After Payment | Order status after IPN confirmation |

## Security

- All API requests are signed with **HMAC-SHA512** and include a timestamp for replay protection.
- IPN signatures are verified using constant-time comparison (`hash_equals`).
- API secrets are stored in the WooCommerce settings database, never exposed to the frontend.

## File Structure

```
gowallet-payment-gateway/
├── gowallet-payment-gateway.php    # Plugin entry point
├── includes/
│   ├── class-gowallet-hmac.php     # HMAC signing & IPN verification
│   ├── class-gowallet-api-client.php  # API HTTP client
│   ├── class-gowallet-gateway.php  # WooCommerce gateway
│   └── class-gowallet-ipn-handler.php # IPN webhook handler
├── assets/
│   └── icon.png                    # Payment method icon
└── README.md
```

## License

MIT

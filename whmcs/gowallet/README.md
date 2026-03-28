# GoWallet Payment Gateway Module for WHMCS

Accept cryptocurrency payments (USDT, USDC, DAI, etc.) on TRON, BSC, Ethereum, and Solana in your WHMCS billing system — powered by **GoWallet**.

## Requirements

- WHMCS 8.0+
- PHP 7.4+
- cURL extension enabled
- A GoWallet account with API credentials (API Key & Secret)

## Installation

1. Download or clone the `gowallet` module folder.
2. Copy the contents into your WHMCS installation:

```
whmcs/
└── modules/
    └── gateways/
        ├── gowallet.php                    ← copy from gowallet/gowallet.php
        ├── gowallet/
        │   └── lib/
        │       ├── GoWalletHMAC.php
        │       └── GoWalletClient.php
        └── callback/
            └── gowallet.php                ← copy from gowallet/callback/gowallet.php
```

3. Log in to the WHMCS Admin Panel.
4. Navigate to **Setup → Payments → Payment Gateways**.
5. Find **GoWallet Crypto Payment** and click **Activate**.
6. Configure:
   - **API URL** — your GoWallet API base URL
   - **API Key** — your HMAC API key
   - **API Secret** — your HMAC API secret
   - **Default Network** — TRON, BSC, ETHEREUM, or SOLANA

## IPN (Webhook) Setup

Set your IPN callback URL in the GoWallet dashboard to:

```
https://yoursite.com/modules/gateways/callback/gowallet.php
```

When a deposit is confirmed on-chain, GoWallet sends a signed `POST` request to this endpoint. The module verifies the HMAC-SHA512 signature, matches the payment to the WHMCS invoice, and applies the payment automatically.

## How It Works

1. Customer views an invoice and sees the **GoWallet Crypto Payment** option.
2. The module calls the GoWallet API to generate a unique deposit wallet for the invoice.
3. The deposit address and network are displayed on the invoice page.
4. Customer sends the cryptocurrency to the address.
5. GoWallet detects the on-chain deposit and sends an IPN webhook.
6. The callback script verifies the signature and marks the invoice as **Paid**.

## Configuration Options

| Setting | Description |
|---------|-------------|
| API URL | GoWallet API base URL |
| API Key | Your HMAC API key |
| API Secret | Your HMAC API secret |
| Default Network | Blockchain network for deposit wallets (TRON, BSC, ETHEREUM, SOLANA) |

## File Structure

```
gowallet/
├── gowallet.php              # Main gateway module (config + payment link)
├── callback/
│   └── gowallet.php          # IPN webhook callback handler
├── lib/
│   ├── GoWalletHMAC.php      # HMAC signing & IPN verification
│   └── GoWalletClient.php    # API HTTP client (cURL)
└── README.md
```

## Security

- All API requests are signed with **HMAC-SHA512** and include a timestamp for replay protection.
- IPN signatures are verified using constant-time comparison (`hash_equals`).
- API secrets are stored securely in the WHMCS encrypted configuration.
- All transactions are logged via WHMCS's `logTransaction()`.

## License

MIT

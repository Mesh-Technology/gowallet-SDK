# GoWallet PHP SDK

Official PHP SDK for the GoWallet API – create wallets and verify IPN webhooks with HMAC-SHA512 authentication.

## Requirements

- PHP ≥ 7.4
- `ext-json`
- `ext-curl`

## Installation

```bash
# Clone the repository
git clone https://github.com/Mesh-Technology/gowallet-SDK.git
```

Add the SDK to your `composer.json` as a local path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../path/to/gowallet-SDK/php"
    }
  ],
  "require": {
    "mesh-technology/gowallet-sdk": "*"
  }
}
```

Then run:

```bash
composer update
```

## Quick Start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use GoWallet\Client;
use GoWallet\GoWalletException;

$client = new Client([
    'base_url'   => 'https://api.example.com',
    'api_key'    => 'your-api-key',
    'api_secret' => 'your-api-secret',
]);

// Create a deposit wallet
try {
    $wallet = $client->createWallet('user-123', 'TRON');
    echo "Address: " . $wallet['address'] . "\n";
} catch (GoWalletException $e) {
    echo "Error {$e->getStatusCode()}: {$e->getMessage()}\n";
}
```

## API Reference

### Constructor

```php
$client = new Client([
    'base_url'   => 'https://api.example.com',  // Required
    'api_key'    => 'your-api-key',              // Required
    'api_secret' => 'your-api-secret',           // Required
    'timeout'    => 30,                          // Optional (seconds)
]);
```

### Wallet

```php
// Generate or retrieve a deposit wallet
$wallet = $client->createWallet('user-123', 'TRON');
// Returns: ['user_id' => '...', 'address' => '...', 'network' => '...', 'created_at' => '...']
```

### Health Check

```php
$health = $client->health();
// Returns: ['status' => 'ok']
```

### Get Networks

```php
$networks = $client->getNetworks();
// Returns: ['networks' => [...], 'count' => 3]
foreach ($networks['networks'] as $net) {
    echo $net['name'] . ': ' . implode(', ', array_column($net['tokens'], 'symbol')) . "\n";
}
```

### IPN Webhook Verification

```php
// In your webhook handler:
$payload = json_decode(file_get_contents('php://input'), true);

if ($client->verifyIPN($payload)) {
    // Signature valid – process the notification
    echo "TX: {$payload['transaction_id']}\n";
} else {
    // Invalid signature – reject
    http_response_code(403);
}
```

### Direct HMAC Functions

```php
use GoWallet\HMAC;

// Sign a payload
$signature = HMAC::signPayload(['key' => 'value'], $secret);

// Verify IPN independently
$valid = HMAC::verifyIPNSignature($payload, $secret);
```

## Error Handling

All API errors throw `GoWalletException`:

```php
use GoWallet\GoWalletException;

try {
    $client->createWallet('user-123', 'UNKNOWN_NETWORK');
} catch (GoWalletException $e) {
    echo "Status: " . $e->getStatusCode() . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Body: " . print_r($e->getBody(), true) . "\n";
}
```

## Authentication

The SDK automatically handles HMAC-SHA512 authentication:

1. Request body is JSON-encoded (compact, no extra whitespace)
2. For GET/DELETE requests, an empty string is used as the message
3. Signature is computed: `HMAC-SHA512(message, api_secret)` → hex
4. Three headers are sent: `HMAC_KEY`, `HMAC_SIGN`, `X-Timestamp`

## License

MIT

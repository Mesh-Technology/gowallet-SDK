# GoWallet SDK

Official SDKs for the [GoWallet](https://github.com/Mesh-Technology/gowallet) Payment Gateway API.

Authenticate with HMAC-SHA512, create deposit wallets, and verify IPN webhooks — in your language of choice.

## Available SDKs

| Language | Directory | Install |
|----------|-----------|---------|
| **Go** | [`golang/`](./golang) | `go get github.com/Mesh-Technology/gowallet-SDK/golang` |
| **Node.js** | [`nodejs/`](./nodejs) | `npm install @gowallet/sdk` |
| **PHP** | [`php/`](./php) | `composer require mesh-technology/gowallet-sdk` |
| **Python** | [`python/`](./python) | `pip install gowallet-sdk` |

## Supported Methods

All SDKs expose the same three operations:

| Method | Auth | Description |
|--------|------|-------------|
| `createWallet` | HMAC | Generate or retrieve a deposit wallet for a user on a network |
| `health` | None | Health check |
| `verifyIPN` | — | Verify the HMAC signature of an incoming IPN webhook payload |

## Authentication

Requests are signed automatically by each SDK. Three headers are sent:

| Header | Description |
|--------|-------------|
| `HMAC_KEY` | Your API key |
| `HMAC_SIGN` | HMAC-SHA512 hex signature of the compact JSON body |
| `X-Timestamp` | Unix timestamp in seconds (must be within ±5 minutes) |

## Quick Start

### Go

```go
client := gowallet.NewClient("https://api.example.com", "key", "secret")
wallet, _ := client.CreateWallet(ctx, gowallet.CreateWalletRequest{
    UserID:  "user-123",
    Network: "BSC",
})
```

### Node.js

```typescript
const client = new GoWalletClient({ baseUrl: "https://api.example.com", apiKey: "key", apiSecret: "secret" });
const wallet = await client.createWallet({ userId: "user-123", network: "BSC" });
```

### PHP

```php
$client = new \GoWallet\Client([
    'base_url'   => 'https://api.example.com',
    'api_key'    => 'key',
    'api_secret' => 'secret',
]);
$wallet = $client->createWallet('user-123', 'BSC');
```

### Python

```python
client = GoWalletClient(base_url="https://api.example.com", api_key="key", api_secret="secret")
wallet = client.create_wallet("user-123", "BSC")
```

## License

MIT

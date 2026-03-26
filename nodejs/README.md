# GoWallet SDK for Node.js

Official Node.js SDK for the GoWallet Payment Gateway API.

## Installation

```bash
npm install @gowallet/sdk
```

## Quick Start

```typescript
import { GoWalletClient } from "@gowallet/sdk";

const client = new GoWalletClient({
  baseUrl: "https://api.example.com",
  apiKey: "your-api-key",
  apiSecret: "your-api-secret",
});

// Generate a deposit wallet
const wallet = await client.createWallet({
  userId: "user-123",
  network: "BSC",
});
console.log("Deposit address:", wallet.address);
```

## Authentication

All API requests are authenticated using HMAC-SHA512. The SDK handles signature generation automatically — just provide your API key and secret.

**Headers sent automatically:**
| Header | Description |
|--------|-------------|
| `HMAC_KEY` | Your API key |
| `HMAC_SIGN` | HMAC-SHA512 hex signature of the compact JSON body |
| `X-Timestamp` | Unix timestamp in seconds (must be within ±5 minutes) |

## IPN Webhook Verification

When you receive an IPN (Instant Payment Notification) webhook, verify the signature:

```typescript
import express from "express";
import { GoWalletClient } from "@gowallet/sdk";

const client = new GoWalletClient({
  baseUrl: "https://api.example.com",
  apiKey: "your-api-key",
  apiSecret: "your-api-secret",
});

app.post("/webhook/ipn", express.json(), (req, res) => {
  if (!client.verifyIPN(req.body)) {
    return res.status(401).json({ error: "Invalid signature" });
  }

  const { transaction_type, amount, token, network, user_id } = req.body;
  console.log(`${transaction_type}: ${amount} ${token} on ${network} for ${user_id}`);

  res.status(200).json({ status: "ok" });
});
```

## API Reference

### `new GoWalletClient(config)`

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `baseUrl` | `string` | Yes | API base URL |
| `apiKey` | `string` | Yes | HMAC API key |
| `apiSecret` | `string` | Yes | HMAC API secret |
| `timeout` | `number` | No | Request timeout in ms (default: 30000) |

### Methods

| Method | Description |
|--------|-------------|
| `createWallet(params)` | Generate/retrieve a deposit wallet |
| `health()` | Health check (no auth) |
| `verifyIPN(payload)` | Verify IPN webhook signature |

## Error Handling

```typescript
import { GoWalletClient, GoWalletAPIError } from "@gowallet/sdk";

try {
  await client.createWallet({ userId: "user-1", network: "BSC" });
} catch (err) {
  if (err instanceof GoWalletAPIError) {
    console.error(`HTTP ${err.statusCode}: ${err.body.error}`);
  }
}
```

## License

MIT

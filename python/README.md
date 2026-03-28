# GoWallet Python SDK

Official Python SDK for the GoWallet API – create wallets and verify IPN webhooks with HMAC-SHA512 authentication.

**Zero dependencies** – uses only the Python standard library.

## Requirements

- Python ≥ 3.8

## Installation

```bash
# Install directly from GitHub
pip install git+https://github.com/Mesh-Technology/gowallet-SDK.git#subdirectory=python

# Or clone and install locally
git clone https://github.com/Mesh-Technology/gowallet-SDK.git
pip install ./gowallet-SDK/python
```

## Quick Start

```python
from gowallet_sdk import GoWalletClient, GoWalletAPIError

client = GoWalletClient(
    base_url="https://api.example.com",
    api_key="your-api-key",
    api_secret="your-api-secret",
)

# Create a deposit wallet
try:
    wallet = client.create_wallet("user-123", "TRON")
    print(f"Address: {wallet['address']}")
except GoWalletAPIError as e:
    print(f"Error {e.status_code}: {e}")
```

## API Reference

### Constructor

```python
client = GoWalletClient(
    base_url="https://api.example.com",   # Required
    api_key="your-api-key",               # Required
    api_secret="your-api-secret",         # Required
    timeout=30,                           # Optional (seconds)
)
```

### Wallet

```python
# Generate or retrieve a deposit wallet
wallet = client.create_wallet("user-123", "TRON")
# Returns: {"user_id": "...", "address": "...", "network": "...", "created_at": "..."}
```

### Health Check

```python
health = client.health()
# Returns: {"status": "ok"}
```

### Get Networks

```python
networks = client.get_networks()
# Returns: {"networks": [...], "count": 3}
for net in networks["networks"]:
    tokens = ", ".join(t["symbol"] for t in net["tokens"])
    print(f"{net['name']}: {tokens}")
```

### IPN Webhook Verification

```python
# In your webhook handler (e.g., Flask):
import json

@app.route("/webhook", methods=["POST"])
def webhook():
    payload = json.loads(request.data)

    if client.verify_ipn(payload):
        # Signature valid – process the notification
        print(f"TX: {payload['transaction_id']}")
        return "OK", 200
    else:
        # Invalid signature – reject
        return "Forbidden", 403
```

### Direct HMAC Functions

```python
from gowallet_sdk import sign_payload, verify_ipn_signature

# Sign a payload
signature = sign_payload({"key": "value"}, secret)

# Verify IPN independently
valid = verify_ipn_signature(payload, secret)
```

## Error Handling

All API errors raise `GoWalletAPIError`:

```python
from gowallet_sdk import GoWalletClient, GoWalletAPIError

try:
    client.create_wallet("user-123", "UNKNOWN_NETWORK")
except GoWalletAPIError as e:
    print(f"Status: {e.status_code}")
    print(f"Message: {e}")
    print(f"Body: {e.body}")
```

## Authentication

The SDK automatically handles HMAC-SHA512 authentication:

1. Request body is JSON-encoded (compact, no extra whitespace)
2. For GET/DELETE requests, an empty string is used as the message
3. Signature is computed: `HMAC-SHA512(message, api_secret)` → hex
4. Three headers are sent: `HMAC_KEY`, `HMAC_SIGN`, `X-Timestamp`

## License

MIT

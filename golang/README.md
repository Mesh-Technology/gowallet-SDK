# GoWallet SDK for Go

Official Go SDK for the GoWallet Payment Gateway API.

## Installation

```bash
go get github.com/Mesh-Technology/gowallet-sdk-go
```

## Quick Start

```go
package main

import (
    "context"
    "fmt"
    "log"

    gowallet "github.com/Mesh-Technology/gowallet-sdk-go"
)

func main() {
    client := gowallet.NewClient(
        "https://api.example.com",
        "your-api-key",
        "your-api-secret",
    )

    ctx := context.Background()

    // Generate a deposit wallet
    wallet, err := client.CreateWallet(ctx, gowallet.CreateWalletRequest{
        UserID:  "user-123",
        Network: "BSC",
    })
    if err != nil {
        log.Fatal(err)
    }
    fmt.Println("Deposit address:", wallet.Address)
}
```

## IPN Webhook Verification

```go
func ipnHandler(w http.ResponseWriter, r *http.Request) {
    var payload map[string]interface{}
    json.NewDecoder(r.Body).Decode(&payload)

    if !client.VerifyIPN(payload) {
        http.Error(w, "Invalid signature", http.StatusUnauthorized)
        return
    }

    fmt.Printf("Received %s: %v %s on %s\n",
        payload["transaction_type"],
        payload["amount"],
        payload["token"],
        payload["network"],
    )
    w.WriteHeader(http.StatusOK)
}
```

## Error Handling

```go
wallet, err := client.CreateWallet(ctx, req)
if err != nil {
    var apiErr *gowallet.APIError
    if errors.As(err, &apiErr) {
        fmt.Printf("HTTP %d: %s\n", apiErr.StatusCode, apiErr.Body.Error)
    }
}
```

## API Reference

| Method | Description |
|--------|-------------|
| `CreateWallet(ctx, req)` | Generate/retrieve a deposit wallet |
| `Health(ctx)` | Health check (no auth) |
| `VerifyIPN(payload)` | Verify IPN webhook signature |

## License

MIT

package gowallet

// Package gowallet provides a Go SDK for the GoWallet Payment Gateway API.
//
// All HMAC-authenticated API requests are signed automatically.
//
//	client := gowallet.NewClient("https://api.example.com", "api-key", "api-secret")
//	wallet, err := client.CreateWallet(ctx, gowallet.CreateWalletRequest{
//	    UserID:  "user-123",
//	    Network: "BSC",
//	})

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/sha512"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strconv"
	"time"
)

// Client communicates with the GoWallet API.
type Client struct {
	baseURL    string
	apiKey     string
	apiSecret  string
	httpClient *http.Client
}

// NewClient creates a new GoWallet API client.
func NewClient(baseURL, apiKey, apiSecret string) *Client {
	return &Client{
		baseURL:   baseURL,
		apiKey:    apiKey,
		apiSecret: apiSecret,
		httpClient: &http.Client{
			Timeout: 30 * time.Second,
		},
	}
}

// SetHTTPClient overrides the default HTTP client.
func (c *Client) SetHTTPClient(hc *http.Client) {
	c.httpClient = hc
}

// ── Wallet ──────────────────────────────────────────────────────────

// CreateWallet generates or retrieves a deposit wallet for a user on a network.
func (c *Client) CreateWallet(ctx context.Context, req CreateWalletRequest) (*WalletResponse, error) {
	var resp WalletResponse
	if err := c.post(ctx, "/api/v1/wallet", req, &resp); err != nil {
		return nil, err
	}
	return &resp, nil
}

// ── Public ──────────────────────────────────────────────────────────

// Health performs a health check (no authentication required).
func (c *Client) Health(ctx context.Context) (*HealthResponse, error) {
	var resp HealthResponse
	if err := c.doRequest(ctx, http.MethodGet, "/health", nil, &resp, false); err != nil {
		return nil, err
	}
	return &resp, nil
}

// ── IPN Verification ────────────────────────────────────────────────

// VerifyIPN checks the HMAC signature of an incoming IPN webhook payload.
// Returns true if the signature is valid.
func (c *Client) VerifyIPN(payload map[string]interface{}) bool {
	return VerifyIPNSignature(payload, c.apiSecret)
}

// ── Exported Helpers ────────────────────────────────────────────────

// SignPayload computes the HMAC-SHA512 hex signature for a request body.
// Pass nil or empty slice for GET/DELETE requests.
func SignPayload(body []byte, secret string) string {
	mac := hmac.New(sha512.New, []byte(secret))
	mac.Write(body)
	return hex.EncodeToString(mac.Sum(nil))
}

// VerifyIPNSignature verifies the HMAC signature of an IPN payload.
func VerifyIPNSignature(payload map[string]interface{}, secret string) bool {
	sig, ok := payload["signature"].(string)
	if !ok {
		return false
	}

	// Build message without signature field
	clean := make(map[string]interface{}, len(payload)-1)
	for k, v := range payload {
		if k != "signature" {
			clean[k] = v
		}
	}

	message, err := json.Marshal(clean)
	if err != nil {
		return false
	}

	mac := hmac.New(sha512.New, []byte(secret))
	mac.Write(message)
	expected, err := hex.DecodeString(hex.EncodeToString(mac.Sum(nil)))
	if err != nil {
		return false
	}

	received, err := hex.DecodeString(sig)
	if err != nil {
		return false
	}

	return hmac.Equal(received, expected)
}

// ── Internal Helpers ────────────────────────────────────────────────

func (c *Client) post(ctx context.Context, path string, body, out interface{}) error {
	return c.doRequest(ctx, http.MethodPost, path, body, out, true)
}

func (c *Client) doRequest(ctx context.Context, method, path string, body, out interface{}, auth bool) error {
	url := c.baseURL + path

	var bodyReader io.Reader
	var bodyBytes []byte

	if body != nil {
		var err error
		bodyBytes, err = json.Marshal(body)
		if err != nil {
			return fmt.Errorf("gowallet: failed to marshal request: %w", err)
		}
		bodyReader = bytes.NewReader(bodyBytes)
	}

	req, err := http.NewRequestWithContext(ctx, method, url, bodyReader)
	if err != nil {
		return fmt.Errorf("gowallet: failed to create request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")

	if auth {
		signature := SignPayload(bodyBytes, c.apiSecret)
		req.Header.Set("HMAC_KEY", c.apiKey)
		req.Header.Set("HMAC_SIGN", signature)
		req.Header.Set("X-Timestamp", strconv.FormatInt(time.Now().Unix(), 10))
	}

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("gowallet: request failed: %w", err)
	}
	defer resp.Body.Close()

	respBody, err := io.ReadAll(resp.Body)
	if err != nil {
		return fmt.Errorf("gowallet: failed to read response: %w", err)
	}

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		var apiErr ErrorResponse
		if json.Unmarshal(respBody, &apiErr) == nil {
			return &APIError{StatusCode: resp.StatusCode, Body: apiErr}
		}
		return &APIError{StatusCode: resp.StatusCode, Body: ErrorResponse{Error: string(respBody)}}
	}

	if out != nil {
		if err := json.Unmarshal(respBody, out); err != nil {
			return fmt.Errorf("gowallet: failed to unmarshal response: %w", err)
		}
	}

	return nil
}

// APIError represents an error returned by the GoWallet API.
type APIError struct {
	StatusCode int
	Body       ErrorResponse
}

func (e *APIError) Error() string {
	if e.Body.Error != "" {
		return fmt.Sprintf("gowallet: HTTP %d: %s", e.StatusCode, e.Body.Error)
	}
	return fmt.Sprintf("gowallet: HTTP %d", e.StatusCode)
}

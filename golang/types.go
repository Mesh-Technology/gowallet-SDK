package gowallet

// CreateWalletRequest represents a request to generate/retrieve a deposit wallet.
type CreateWalletRequest struct {
	UserID  string `json:"userId"`
	Network string `json:"network"`
}

// WalletResponse is returned when a wallet is created or retrieved.
type WalletResponse struct {
	UserID    string `json:"user_id"`
	Address   string `json:"address"`
	Network   string `json:"network"`
	CreatedAt string `json:"created_at"`
}

// HealthResponse is the health check response.
type HealthResponse struct {
	Status string `json:"status"`
}

// PublicToken represents a token on a public network.
type PublicToken struct {
	Symbol          string `json:"symbol"`
	ContractAddress string `json:"contract_address,omitempty"`
	Decimal         int    `json:"decimal"`
}

// PublicNetwork represents an active network with its tokens.
type PublicNetwork struct {
	ID       uint          `json:"id"`
	Name     string        `json:"name"`
	Protocol string        `json:"protocol"`
	Tokens   []PublicToken `json:"tokens"`
}

// NetworksResponse is the response from GetNetworks.
type NetworksResponse struct {
	Networks []PublicNetwork `json:"networks"`
	Count    int             `json:"count"`
}

// ErrorResponse represents an API error.
type ErrorResponse struct {
	Error   string `json:"error"`
	Details string `json:"details,omitempty"`
}

// IPNPayload represents an incoming IPN webhook payload.
type IPNPayload struct {
	ID                int     `json:"id"`
	TransactionID     string  `json:"transaction_id"`
	TransactionType   string  `json:"transaction_type"`
	UserID            string  `json:"user_id"`
	ClientID          int     `json:"client_id"`
	FromAddress       string  `json:"from_address"`
	ToAddress         string  `json:"to_address"`
	Amount            float64 `json:"amount"`
	Token             string  `json:"token"`
	Network           string  `json:"network"`
	BlockNumber       int64   `json:"block_number"`
	BlockTimestamp    string  `json:"block_timestamp"`
	ConfirmationCount int     `json:"confirmation_count"`
	Signature         string  `json:"signature"`
	CreatedAt         string  `json:"created_at"`
}

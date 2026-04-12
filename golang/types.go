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

// ── Invoice Types ──

// CreateInvoiceRequest is the request to create a new invoice.
type CreateInvoiceRequest struct {
	PriceAmount   float64 `json:"price_amount"`
	PriceCurrency string  `json:"price_currency"`
	OrderID       string  `json:"order_id,omitempty"`
	Title         string  `json:"title,omitempty"`
	Description   string  `json:"description,omitempty"`
	CallbackURL   string  `json:"callback_url,omitempty"`
	SuccessURL    string  `json:"success_url,omitempty"`
	CancelURL     string  `json:"cancel_url,omitempty"`
}

// SelectPayCurrencyRequest is the request to select a payment currency for an invoice.
type SelectPayCurrencyRequest struct {
	PayCurrency string `json:"pay_currency"`
	PayNetwork  string `json:"pay_network"`
}

// InvoiceResponse represents an invoice returned by the API.
type InvoiceResponse struct {
	ID                uint     `json:"id"`
	InvoiceID         string   `json:"invoice_id"`
	ClientID          uint     `json:"client_id"`
	UserID            string   `json:"user_id"`
	PriceAmount       float64  `json:"price_amount"`
	PriceCurrency     string   `json:"price_currency"`
	PayCurrency       *string  `json:"pay_currency"`
	PayAmount         *float64 `json:"pay_amount"`
	PayNetwork        *string  `json:"pay_network"`
	PayAddress        string   `json:"pay_address,omitempty"`
	ActuallyPaid      float64  `json:"actually_paid"`
	UnderpaidAmount   float64  `json:"underpaid_amount"`
	OverpaidAmount    float64  `json:"overpaid_amount"`
	OrderID           *string  `json:"order_id"`
	Title             *string  `json:"title"`
	Description       *string  `json:"description"`
	Status            string   `json:"status"`
	UnderpaidThreshold float64 `json:"underpaid_threshold"`
	ExpiresAt         *string  `json:"expires_at"`
	PaidAt            *string  `json:"paid_at"`
	CreatedAt         string   `json:"created_at"`
	UpdatedAt         string   `json:"updated_at"`
}

// InvoiceListResponse is the paginated response for listing invoices.
type InvoiceListResponse struct {
	Invoices   []InvoiceResponse `json:"invoices"`
	Total      int64             `json:"total"`
	Page       int               `json:"page"`
	Limit      int               `json:"limit"`
	TotalPages int               `json:"total_pages"`
}

// InvoicePayment represents an on-chain payment for an invoice.
type InvoicePayment struct {
	ID                    uint    `json:"id"`
	InvoiceID             uint    `json:"invoice_id"`
	TransactionHash       string  `json:"transaction_hash"`
	FromAddress           string  `json:"from_address"`
	Amount                float64 `json:"amount"`
	Currency              string  `json:"currency"`
	Network               string  `json:"network"`
	Confirmations         int     `json:"confirmations"`
	RequiredConfirmations int     `json:"required_confirmations"`
	Status                string  `json:"status"`
	DetectedAt            string  `json:"detected_at"`
	ConfirmedAt           *string `json:"confirmed_at"`
}

// InvoiceStatusHistoryEntry is a single status transition for an invoice.
type InvoiceStatusHistoryEntry struct {
	FromStatus *string `json:"from_status"`
	ToStatus   string  `json:"to_status"`
	Reason     *string `json:"reason"`
	CreatedAt  string  `json:"created_at"`
}

// InvoiceDetailResponse is the full invoice detail with payments and history.
type InvoiceDetailResponse struct {
	Invoice       InvoiceResponse             `json:"invoice"`
	Payments      []InvoicePayment            `json:"payments"`
	StatusHistory []InvoiceStatusHistoryEntry  `json:"status_history"`
}

// InvoiceCallbackPayload represents the callback webhook payload for invoice events.
type InvoiceCallbackPayload struct {
	InvoiceID     string  `json:"invoice_id"`
	OrderID       string  `json:"order_id,omitempty"`
	Status        string  `json:"status"`
	PriceAmount   string  `json:"price_amount"`
	PriceCurrency string  `json:"price_currency"`
	PayCurrency   string  `json:"pay_currency,omitempty"`
	PayAmount     string  `json:"pay_amount,omitempty"`
	PayNetwork    string  `json:"pay_network,omitempty"`
	PayAddress    string  `json:"pay_address,omitempty"`
	ActuallyPaid  string  `json:"actually_paid"`
	CreatedAt     string  `json:"created_at"`
	PaidAt        string  `json:"paid_at,omitempty"`
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

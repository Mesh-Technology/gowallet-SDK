export interface GoWalletConfig {
  /** Base URL of the GoWallet API (e.g. "https://api.example.com") */
  baseUrl: string;
  /** HMAC API Key (UUID format) */
  apiKey: string;
  /** HMAC API Secret */
  apiSecret: string;
  /** Request timeout in milliseconds (default: 30000) */
  timeout?: number;
}

// ── Request Types ──

export interface CreateWalletRequest {
  userId: string;
  network: "TRON" | "BSC" | "ETHEREUM" | "SOLANA" | string;
}

// ── Response Types ──

export interface WalletResponse {
  user_id: string;
  address: string;
  network: string;
  created_at: string;
}

export interface HealthResponse {
  status: string;
}

export interface PublicToken {
  symbol: string;
  contract_address?: string;
  decimal: number;
}

export interface PublicNetwork {
  id: number;
  name: string;
  protocol: string;
  tokens: PublicToken[];
}

export interface NetworksResponse {
  networks: PublicNetwork[];
  count: number;
}

// ── Invoice Types ──

export interface CreateInvoiceRequest {
  price_amount: number;
  price_currency: string;
  order_id?: string;
  title?: string;
  description?: string;
  callback_url?: string;
  success_url?: string;
  cancel_url?: string;
}

export interface SelectPayCurrencyRequest {
  pay_currency: string;
  pay_network: "TRON" | "BSC" | "ETHEREUM" | "SOLANA" | string;
}

export interface InvoiceResponse {
  id: number;
  invoice_id: string;
  client_id: number;
  user_id: string;
  price_amount: number;
  price_currency: string;
  pay_currency: string | null;
  pay_amount: number | null;
  pay_network: string | null;
  pay_address: string | null;
  actually_paid: number;
  underpaid_amount: number;
  overpaid_amount: number;
  pool_increased: boolean;
  order_id: string | null;
  title: string | null;
  description: string | null;
  status: string;
  underpaid_threshold: number;
  expires_at: string | null;
  paid_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface InvoiceListResponse {
  invoices: InvoiceResponse[];
  total: number;
  page: number;
  limit: number;
  total_pages: number;
}

export interface InvoicePayment {
  id: number;
  invoice_id: number;
  transaction_hash: string;
  from_address: string;
  amount: number;
  currency: string;
  network: string;
  confirmations: number;
  required_confirmations: number;
  status: string;
  detected_at: string;
  confirmed_at: string | null;
}

export interface InvoiceStatusHistoryEntry {
  from_status: string | null;
  to_status: string;
  reason: string | null;
  created_at: string;
}

export interface InvoiceDetailResponse {
  invoice: InvoiceResponse;
  payments: InvoicePayment[];
  status_history: InvoiceStatusHistoryEntry[];
}

// ── IPN Types ──

export interface IPNPayload {
  id: number;
  transaction_id: string;
  transaction_type: string;
  user_id: string;
  client_id: number;
  from_address: string;
  to_address: string;
  amount: number;
  token: string;
  network: string;
  block_number: number;
  block_timestamp: string;
  confirmation_count: number;
  payload_json?: string;
  signature: string;
  created_at: string;
}

export interface GoWalletError {
  error: string;
  details?: string;
}

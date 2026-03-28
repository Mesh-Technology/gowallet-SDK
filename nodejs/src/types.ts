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

import { request as httpsRequest } from "https";
import { request as httpRequest } from "http";
import { URL } from "url";
import { signPayload, verifyIPNSignature } from "./hmac";
import type {
  GoWalletConfig,
  CreateWalletRequest,
  WalletResponse,
  HealthResponse,
  NetworksResponse,
  GoWalletError,
  CreateInvoiceRequest,
  SelectPayCurrencyRequest,
  InvoiceResponse,
  InvoiceListResponse,
  InvoiceDetailResponse,
} from "./types";

export class GoWalletClient {
  private readonly baseUrl: string;
  private readonly apiKey: string;
  private readonly apiSecret: string;
  private readonly timeout: number;

  constructor(config: GoWalletConfig) {
    if (!config.baseUrl) throw new Error("baseUrl is required");
    if (!config.apiKey) throw new Error("apiKey is required");
    if (!config.apiSecret) throw new Error("apiSecret is required");

    this.baseUrl = config.baseUrl.replace(/\/+$/, "");
    this.apiKey = config.apiKey;
    this.apiSecret = config.apiSecret;
    this.timeout = config.timeout ?? 30_000;
  }

  // ── Wallet ──

  /** Generate or retrieve a deposit wallet for a user on a network. */
  async createWallet(params: CreateWalletRequest): Promise<WalletResponse> {
    return this.post<WalletResponse>("/api/v1/wallet", params as unknown as Record<string, unknown>);
  }

  // ── Invoices ──

  /** Create a new payment invoice. */
  async createInvoice(params: CreateInvoiceRequest): Promise<InvoiceResponse> {
    return this.post<InvoiceResponse>("/api/v1/invoices", params as unknown as Record<string, unknown>);
  }

  /** Get invoice detail by UUID. */
  async getInvoice(invoiceId: string): Promise<InvoiceDetailResponse> {
    return this.get<InvoiceDetailResponse>(`/api/v1/invoices/${invoiceId}`);
  }

  /** List invoices with optional query parameters. */
  async listInvoices(params?: Record<string, string | number>): Promise<InvoiceListResponse> {
    let path = "/api/v1/invoices";
    if (params) {
      const query = Object.entries(params)
        .filter(([, v]) => v !== undefined && v !== "")
        .map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`)
        .join("&");
      if (query) path += `?${query}`;
    }
    return this.get<InvoiceListResponse>(path);
  }

  /** Select payment currency and network for an invoice (new -> pending). */
  async selectPayCurrency(invoiceId: string, params: SelectPayCurrencyRequest): Promise<InvoiceResponse> {
    return this.post<InvoiceResponse>(`/api/v1/invoices/${invoiceId}/select`, params as unknown as Record<string, unknown>);
  }

  /** Cancel an invoice (only from new/pending status). */
  async cancelInvoice(invoiceId: string): Promise<InvoiceResponse> {
    return this.post<InvoiceResponse>(`/api/v1/invoices/${invoiceId}/cancel`, {});
  }

  /** Verify the HMAC-SHA512 signature of an invoice callback payload. */
  verifyInvoiceCallback(payload: Record<string, unknown>, signature: string): boolean {
    const expected = signPayload(payload, this.apiSecret);
    return expected === signature;
  }

  // ── Public (no auth) ──

  /** Health check. */
  async health(): Promise<HealthResponse> {
    return this.request<HealthResponse>("GET", "/health", undefined, false);
  }

  /** Get all active networks and their tokens (no auth required). */
  async getNetworks(): Promise<NetworksResponse> {
    return this.request<NetworksResponse>("GET", "/api/v1/public/networks", undefined, false);
  }

  // ── IPN Verification ──

  /**
   * Verify the HMAC signature of an incoming IPN webhook payload.
   * Returns true if the signature is valid.
   */
  verifyIPN(payload: Record<string, unknown>): boolean {
    return verifyIPNSignature(payload, this.apiSecret);
  }

  // ── Internal HTTP ──

  private async get<T>(path: string): Promise<T> {
    return this.request<T>("GET", path, undefined, true);
  }

  private async post<T>(
    path: string,
    body: Record<string, unknown>
  ): Promise<T> {
    return this.request<T>("POST", path, body, true);
  }

  private request<T>(
    method: string,
    path: string,
    body?: Record<string, unknown>,
    auth = true
  ): Promise<T> {
    return new Promise((resolve, reject) => {
      const url = new URL(path, this.baseUrl);
      const isHttps = url.protocol === "https:";
      const transport = isHttps ? httpsRequest : httpRequest;

      const headers: Record<string, string> = {
        "Content-Type": "application/json",
        Accept: "application/json",
      };

      let payload = "";
      if (body) {
        payload = JSON.stringify(body);
      }

      if (auth) {
        const signature = signPayload(
          body ?? "",
          this.apiSecret
        );
        headers["HMAC_KEY"] = this.apiKey;
        headers["HMAC_SIGN"] = signature;
        headers["X-Timestamp"] = Math.floor(Date.now() / 1000).toString();
      }

      const req = transport(
        {
          hostname: url.hostname,
          port: url.port || (isHttps ? 443 : 80),
          path: url.pathname + url.search,
          method,
          headers,
          timeout: this.timeout,
        },
        (res) => {
          const chunks: Buffer[] = [];
          res.on("data", (chunk: Buffer) => chunks.push(chunk));
          res.on("end", () => {
            const raw = Buffer.concat(chunks).toString("utf-8");
            const status = res.statusCode ?? 0;

            let parsed: unknown;
            try {
              parsed = JSON.parse(raw);
            } catch {
              if (status >= 200 && status < 300) {
                resolve(raw as unknown as T);
                return;
              }
              reject(new GoWalletAPIError(status, { error: raw }));
              return;
            }

            if (status >= 200 && status < 300) {
              resolve(parsed as T);
            } else {
              reject(
                new GoWalletAPIError(status, parsed as GoWalletError)
              );
            }
          });
        }
      );

      req.on("error", reject);
      req.on("timeout", () => {
        req.destroy();
        reject(new Error("Request timed out"));
      });

      if (payload) {
        req.write(payload);
      }
      req.end();
    });
  }
}

export class GoWalletAPIError extends Error {
  public readonly statusCode: number;
  public readonly body: GoWalletError;

  constructor(statusCode: number, body: GoWalletError) {
    super(body.error || `HTTP ${statusCode}`);
    this.name = "GoWalletAPIError";
    this.statusCode = statusCode;
    this.body = body;
  }
}

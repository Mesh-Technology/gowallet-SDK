import { createHmac, timingSafeEqual } from "crypto";

/**
 * Generate HMAC-SHA512 signature for a request payload.
 *
 * @param payload  - The request body object (use empty string for GET/DELETE)
 * @param secret   - The API secret key
 * @returns The hex-encoded HMAC-SHA512 signature
 */
export function signPayload(
  payload: Record<string, unknown> | string,
  secret: string
): string {
  const message =
    typeof payload === "string" ? payload : JSON.stringify(payload);

  return createHmac("sha512", secret).update(message).digest("hex");
}

/**
 * Verify an IPN webhook signature.
 *
 * @param payload   - The full IPN payload including signature
 * @param secret    - Your API secret
 * @returns true if the signature is valid
 */
export function verifyIPNSignature(
  payload: Record<string, unknown>,
  secret: string
): boolean {
  const received = payload.signature;
  if (typeof received !== "string") return false;

  // Build message from all fields except signature
  const { signature: _, ...rest } = payload;
  const message = JSON.stringify(rest);
  const expected = createHmac("sha512", secret).update(message).digest("hex");

  // Constant-time comparison
  const a = Buffer.from(received, "hex");
  const b = Buffer.from(expected, "hex");
  if (a.length !== b.length) return false;
  return timingSafeEqual(a, b);
}

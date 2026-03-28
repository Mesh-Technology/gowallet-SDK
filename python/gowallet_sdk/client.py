import json
import time
from typing import Any, Dict, List, Optional, Union
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError

from gowallet_sdk.hmac_auth import sign_payload, verify_ipn_signature
from gowallet_sdk.exceptions import GoWalletAPIError


class GoWalletClient:
    """GoWallet API client with HMAC-SHA512 authentication.

    Args:
        base_url: Base URL of the GoWallet API (e.g. "https://api.example.com").
        api_key: HMAC API key (UUID).
        api_secret: HMAC API secret.
        timeout: Request timeout in seconds (default: 30).
    """

    def __init__(
        self,
        base_url: str,
        api_key: str,
        api_secret: str,
        timeout: int = 30,
    ):
        if not base_url:
            raise ValueError("base_url is required")
        if not api_key:
            raise ValueError("api_key is required")
        if not api_secret:
            raise ValueError("api_secret is required")

        self.base_url = base_url.rstrip("/")
        self.api_key = api_key
        self.api_secret = api_secret
        self.timeout = timeout

    # ── Wallet ──

    def create_wallet(self, user_id: str, network: str) -> Dict[str, Any]:
        """Generate or retrieve a deposit wallet for a user on a network.

        Args:
            user_id: The user identifier.
            network: Network name (TRON, BSC, ETHEREUM, SOLANA, etc.)

        Returns:
            Dict with user_id, address, network, created_at.
        """
        return self._post("/api/v1/wallet", {
            "userId": user_id,
            "network": network,
        })

    # ── Public (no auth) ──

    def health(self) -> Dict[str, Any]:
        """Health check (no auth required).

        Returns:
            Dict with status.
        """
        return self._request("GET", "/health", auth=False)

    def get_networks(self) -> Dict[str, Any]:
        """Get all active networks and their tokens (no auth required).

        Returns:
            Dict with networks list and count.
        """
        return self._request("GET", "/api/v1/public/networks", auth=False)

    # ── IPN Verification ──

    def verify_ipn(self, payload: Dict[str, Any]) -> bool:
        """Verify the HMAC signature of an incoming IPN webhook payload.

        Args:
            payload: The full IPN payload dict including signature.

        Returns:
            True if the signature is valid.
        """
        return verify_ipn_signature(payload, self.api_secret)

    # ── Internal HTTP ──

    def _get(self, path: str) -> Dict[str, Any]:
        return self._request("GET", path, auth=True)

    def _post(self, path: str, body: Dict[str, Any]) -> Dict[str, Any]:
        return self._request("POST", path, body=body, auth=True)

    def _request(
        self,
        method: str,
        path: str,
        body: Optional[Dict[str, Any]] = None,
        auth: bool = True,
    ) -> Dict[str, Any]:
        url = self.base_url + path

        headers = {
            "Content-Type": "application/json",
            "Accept": "application/json",
        }

        payload_bytes = b""
        if body is not None:
            payload_bytes = json.dumps(
                body, separators=(",", ":"), ensure_ascii=False
            ).encode("utf-8")

        if auth:
            signature = sign_payload(body if body is not None else "", self.api_secret)
            headers["HMAC_KEY"] = self.api_key
            headers["HMAC_SIGN"] = signature
            headers["X-Timestamp"] = str(int(time.time()))

        req = Request(
            url,
            data=payload_bytes if payload_bytes else None,
            headers=headers,
            method=method,
        )

        try:
            with urlopen(req, timeout=self.timeout) as resp:
                raw = resp.read().decode("utf-8")
                try:
                    return json.loads(raw)
                except json.JSONDecodeError:
                    return {"raw": raw}
        except HTTPError as e:
            raw = e.read().decode("utf-8")
            try:
                body_parsed = json.loads(raw)
            except json.JSONDecodeError:
                body_parsed = {"error": raw}
            raise GoWalletAPIError(e.code, body_parsed) from None
        except URLError as e:
            raise GoWalletAPIError(0, {"error": str(e.reason)}) from None

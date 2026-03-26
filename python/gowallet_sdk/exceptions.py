from typing import Any, Dict, Optional


class GoWalletAPIError(Exception):
    """Raised when the GoWallet API returns a non-2xx status code."""

    def __init__(self, status_code: int, body: Optional[Dict[str, Any]] = None):
        self.status_code = status_code
        self.body = body or {}
        message = self.body.get("error", f"HTTP {status_code}")
        super().__init__(message)

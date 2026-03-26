import hmac
import hashlib
import json
from typing import Any, Dict, Union


def sign_payload(payload: Union[str, Dict[str, Any]], secret: str) -> str:
    """Generate HMAC-SHA512 signature for a request payload.

    Args:
        payload: The request body dict (for POST) or empty string (for GET/DELETE).
        secret: The API secret key.

    Returns:
        Hex-encoded HMAC-SHA512 signature.
    """
    if isinstance(payload, str):
        message = payload
    else:
        message = json.dumps(payload, separators=(",", ":"), ensure_ascii=False)

    return hmac.new(
        secret.encode("utf-8"),
        message.encode("utf-8"),
        hashlib.sha512,
    ).hexdigest()


def verify_ipn_signature(payload: Dict[str, Any], secret: str) -> bool:
    """Verify an IPN webhook signature using constant-time comparison.

    Args:
        payload: The full IPN payload dict including 'signature' key.
        secret: Your API secret.

    Returns:
        True if the signature is valid.
    """
    received = payload.get("signature")
    if not isinstance(received, str):
        return False

    # Build message from all fields except signature
    data = {k: v for k, v in payload.items() if k != "signature"}
    message = json.dumps(data, separators=(",", ":"), ensure_ascii=False)

    expected = hmac.new(
        secret.encode("utf-8"),
        message.encode("utf-8"),
        hashlib.sha512,
    ).hexdigest()

    return hmac.compare_digest(expected, received)

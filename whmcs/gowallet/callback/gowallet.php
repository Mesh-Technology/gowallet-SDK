<?php
/**
 * GoWallet IPN Callback Handler for WHMCS.
 *
 * URL: https://yoursite.com/modules/gateways/callback/gowallet.php
 *
 * @package GoWallet
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../gowallet/lib/GoWalletHMAC.php';

$gatewayModuleName = 'gowallet';
$gatewayParams     = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die('Module Not Activated');
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (empty($payload)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid payload']));
}

// Verify HMAC signature
$apiSecret = $gatewayParams['apiSecret'];

if (!GoWalletHMAC::verifyIPN($payload, $apiSecret)) {
    http_response_code(403);
    logTransaction($gatewayModuleName, $payload, 'Invalid Signature');
    die(json_encode(['error' => 'Invalid signature']));
}

$userId = $payload['user_id'] ?? '';
$amount = floatval($payload['amount'] ?? 0);
$token  = $payload['token'] ?? '';
$txId   = $payload['transaction_id'] ?? '';

// user_id format: "whmcs-{invoiceId}"
if (strpos($userId, 'whmcs-') !== 0) {
    http_response_code(400);
    logTransaction($gatewayModuleName, $payload, 'Unknown user_id format');
    die(json_encode(['error' => 'Unknown user_id format']));
}

$invoiceId = intval(substr($userId, 6));

// Validate the invoice exists and is unpaid
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayModuleName);

// Check for duplicate transaction
checkCbTransID($txId);

// Apply the payment
addInvoicePayment(
    $invoiceId,
    $txId,
    $amount,
    0,              // fees
    $gatewayModuleName
);

logTransaction($gatewayModuleName, $payload, 'Successful');

http_response_code(200);
echo json_encode(['status' => 'ok']);

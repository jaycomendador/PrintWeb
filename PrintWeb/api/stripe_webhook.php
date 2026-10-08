<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/stripe.php';

header('Content-Type: application/json; charset=utf-8');

$secret = stripeWebhookSecret();
if ($secret === '') {
    error_log('Stripe webhook secret is not configured.');
    http_response_code(500);
    echo json_encode(['error' => 'Webhook is not configured.']);
    exit;
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
if ($payload === false || !verifyStripeWebhookSignature($payload, $signature, $secret)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook signature.']);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event) || !isset($event['type'], $event['data']['object'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event payload.']);
    exit;
}

$session = $event['data']['object'];
try {
    switch ($event['type']) {
        case 'checkout.session.completed':
        case 'checkout.session.async_payment_succeeded':
            processStripeCheckoutSession($session);
            break;
        case 'checkout.session.async_payment_failed':
        case 'checkout.session.expired':
            processStripeCheckoutSession($session, true);
            break;
    }
} catch (RuntimeException $exception) {
    error_log('Stripe webhook processing failed: ' . $exception->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Unable to reconcile payment.']);
    exit;
}

http_response_code(200);
echo json_encode(['received' => true]);

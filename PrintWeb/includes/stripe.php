<?php
require_once __DIR__ . '/functions.php';

function stripeSecretKey(): string {
    return trim((string)(getenv('STRIPE_SECRET_KEY') ?: ''));
}

function stripeWebhookSecret(): string {
    return trim((string)(getenv('STRIPE_WEBHOOK_SECRET') ?: ''));
}

function stripeIsConfigured(): bool {
    $scheme = parse_url(APP_URL, PHP_URL_SCHEME);
    $host = parse_url(APP_URL, PHP_URL_HOST);
    $secureUrl = $scheme === 'https'
        || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true));
    return stripeSecretKey() !== '' && stripeWebhookSecret() !== '' && $secureUrl;
}

function stripeApiRequest(string $method, string $path, array $parameters = [], ?string $idempotencyKey = null): array {
    $secretKey = stripeSecretKey();
    if ($secretKey === '') {
        throw new RuntimeException('Stripe payments are not configured. Ask the administrator to finish setup.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The server is missing the PHP cURL extension required for Stripe.');
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    if ($method === 'GET' && $parameters) {
        $url .= '?' . http_build_query($parameters);
    }

    $headers = ['Authorization: Bearer ' . $secretKey];
    if ($idempotencyKey !== null) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($parameters));
    }

    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($responseBody === false) {
        error_log('Stripe API transport error: ' . $curlError);
        throw new RuntimeException('Could not connect to Stripe. Please try again.');
    }

    $response = json_decode($responseBody, true);
    if (!is_array($response) || $httpStatus < 200 || $httpStatus >= 300) {
        $stripeMessage = is_array($response) ? ($response['error']['message'] ?? 'Invalid Stripe response') : 'Invalid Stripe response';
        error_log('Stripe API request failed (' . $httpStatus . '): ' . $stripeMessage);
        throw new RuntimeException('Stripe could not process the checkout request. Please check the payment details and try again.');
    }

    return $response;
}

function createStripeCheckoutSession(array $job, string $paymentId): array {
    global $pdo;

    $amount = (int)round((float)$job['total_cost'] * 100);
    if ($amount < 1) {
        throw new RuntimeException('The print total must be greater than zero to use Stripe Checkout.');
    }

    $currency = strtolower(getSetting('currency_code', 'php'));
    if (!preg_match('/^[a-z]{3}$/', $currency)) {
        throw new RuntimeException('The configured Stripe currency code is invalid.');
    }

    $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $userStmt->execute([$job['user_id']]);
    $email = $userStmt->fetchColumn();
    if (!$email) {
        throw new RuntimeException('The account for this print job could not be found.');
    }

    $jobUrl = APP_URL . BASE_URL . '/user/payment.php?job=' . rawurlencode($job['job_id']);
    return stripeApiRequest('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'success_url' => $jobUrl . '&payment=return&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $jobUrl . '&payment=cancelled',
        'customer_email' => $email,
        'client_reference_id' => $paymentId,
        'metadata' => [
            'payment_id' => $paymentId,
            'job_id' => (string)$job['job_id'],
        ],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => $amount,
                'product_data' => [
                    'name' => 'Print job ' . $job['job_id'],
                ],
            ],
        ]],
    ], $paymentId);
}

function retrieveStripeCheckoutSession(string $sessionId): array {
    return stripeApiRequest('GET', 'checkout/sessions/' . rawurlencode($sessionId));
}

function processStripeCheckoutSession(array $session, bool $failed = false): string {
    global $pdo;

    $sessionId = (string)($session['id'] ?? '');
    if ($sessionId === '') {
        throw new RuntimeException('Stripe checkout session has no ID.');
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        SELECT pay.*, pj.id AS print_job_id, pj.job_id AS public_job_id, pj.user_id AS job_user_id
        FROM payments pay
        JOIN print_jobs pj ON pj.id = pay.job_id
        WHERE pay.stripe_checkout_session_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$sessionId]);
    $payment = $stmt->fetch();
    if (!$payment) {
        $pdo->rollBack();
        throw new RuntimeException('Stripe checkout session is not linked to a local payment.');
    }

    if (($session['metadata']['payment_id'] ?? '') !== $payment['payment_id']
        || ($session['metadata']['job_id'] ?? '') !== $payment['public_job_id']) {
        $pdo->rollBack();
        throw new RuntimeException('Stripe checkout metadata does not match the local payment.');
    }

    if ($failed) {
        if ($payment['payment_status'] === 'processing') {
            $pdo->prepare("UPDATE payments SET payment_status = 'failed', checkout_url = NULL WHERE id = ?")
                ->execute([$payment['id']]);
            $pdo->prepare("UPDATE print_jobs SET payment_status = 'pending', updated_at = NOW() WHERE id = ? AND payment_status <> 'successful'")
                ->execute([$payment['print_job_id']]);
        }
        $pdo->commit();
        return 'failed';
    }

    if (($session['payment_status'] ?? '') !== 'paid') {
        $pdo->commit();
        return 'processing';
    }

    $expectedAmount = (int)round((float)$payment['amount'] * 100);
    $expectedCurrency = strtolower(getSetting('currency_code', 'php'));
    if ((int)($session['amount_total'] ?? -1) !== $expectedAmount
        || strtolower((string)($session['currency'] ?? '')) !== $expectedCurrency) {
        $pdo->rollBack();
        throw new RuntimeException('Stripe amount or currency does not match the local payment.');
    }

    if ($payment['payment_status'] === 'successful') {
        $pdo->commit();
        return 'successful';
    }
    if ($payment['payment_status'] !== 'processing') {
        $pdo->commit();
        return 'ignored';
    }

    $paymentIntent = $session['payment_intent'] ?? null;
    if (is_array($paymentIntent)) {
        $paymentIntent = $paymentIntent['id'] ?? null;
    }
    $pdo->prepare("
        UPDATE payments
        SET payment_status = 'successful', transaction_reference = ?, paid_at = NOW(), checkout_url = NULL
        WHERE id = ?
    ")->execute([is_string($paymentIntent) ? $paymentIntent : null, $payment['id']]);
    $pdo->prepare("
        UPDATE print_jobs
        SET payment_status = 'successful', print_status = 'queued', payment_at = NOW(), queued_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ")->execute([$payment['print_job_id']]);

    $deviceStmt = $pdo->query("
        SELECT d.id
        FROM esp32_devices d
        JOIN printers p ON p.esp32_id = d.id
        WHERE d.connection_status = 'connected' AND p.status = 'available' AND p.enabled = 1
        ORDER BY p.id
        LIMIT 1
    ");
    $deviceId = $deviceStmt->fetchColumn();
    if ($deviceId) {
        sendEsp32Command((int)$deviceId, 'green', 'beep_payment');
    }

    createNotification(
        (int)$payment['job_user_id'],
        'payment_success',
        'Payment Successful',
        "Payment for job {$payment['public_job_id']} was successful. Your print job has been added to the queue.",
        (int)$payment['print_job_id']
    );
    notifyAdmins(
        'payment_success',
        'New Payment Received',
        "Payment for job {$payment['public_job_id']} was successful.",
        (int)$payment['print_job_id']
    );
    $pdo->commit();

    return 'successful';
}

function verifyStripeWebhookSignature(string $payload, string $signatureHeader, string $secret): bool {
    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $signatureHeader) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key === 't' && ctype_digit($value)) {
            $timestamp = (int)$value;
        } elseif ($key === 'v1' && $value !== '') {
            $signatures[] = $value;
        }
    }

    if ($timestamp === null || abs(time() - $timestamp) > 300 || !$signatures) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }
    return false;
}

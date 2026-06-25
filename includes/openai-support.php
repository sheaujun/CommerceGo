<?php

function supportAiConfig(): array
{
    $config = [];
    $configFile = __DIR__ . '/ai-config.php';

    if (is_file($configFile)) {
        $loadedConfig = require $configFile;
        if (is_array($loadedConfig)) {
            $config = $loadedConfig;
        }
    }

    return [
        'provider' => strtolower(trim((string) (getenv('AI_PROVIDER') ?: ($config['provider'] ?? 'gemini')))),
        'api_key' => trim((string) (getenv('GEMINI_API_KEY') ?: ($config['api_key'] ?? ''))),
        'model' => trim((string) (getenv('GEMINI_MODEL') ?: ($config['model'] ?? 'gemini-2.5-flash'))),
    ];
}

function supportAiFallback(string $message, bool $isQuotaError = false): array
{
    return [
        'ok' => false,
        'message' => $isQuotaError
            ? 'AI Quick Help has reached its usage limit. Please try again later or send your message to our support team.'
            : $message,
    ];
}

function supportAiLogError(array $config, int $statusCode, string $curlError, array $errorBody): void
{
    $providerStatus = trim((string) ($errorBody['error']['status'] ?? ''));
    $providerMessage = trim((string) ($errorBody['error']['message'] ?? ''));
    $providerMessage = preg_replace('/\s+/', ' ', $providerMessage) ?? '';

    error_log(sprintf(
        'Support AI request failed: provider=%s model=%s http=%d curl=%s provider_status=%s provider_message=%s',
        $config['provider'],
        $config['model'],
        $statusCode,
        $curlError,
        $providerStatus,
        substr($providerMessage, 0, 500)
    ));
}

function getSupportAiReply(string $message, array $orders, array $productMatches = []): array
{
    $config = supportAiConfig();
    if ($config['provider'] !== 'gemini') {
        error_log('Support AI configuration error: unsupported provider=' . $config['provider']);
        return supportAiFallback('AI Quick Help is not configured yet. Please send your message to our support team.');
    }

    if ($config['api_key'] === '' || $config['api_key'] === 'your-gemini-api-key') {
        return supportAiFallback('AI Quick Help is not configured yet. Please send your message to our support team.');
    }

    if (!function_exists('curl_init')) {
        return supportAiFallback('AI Quick Help is unavailable because PHP cURL is not enabled. Please send your message to our support team.');
    }

    $orderSummary = empty($orders)
        ? 'No active orders are available for this customer.'
        : json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $productSummary = empty($productMatches)
        ? 'No matching customer-visible product was found in the live catalogue.'
        : json_encode(array_map(static function (array $product): array {
            return [
                'name' => (string) $product['productName'],
                'price_rm' => number_format((float) $product['price'], 2, '.', ''),
                'availability' => (int) $product['stockQuantity'] > 0 ? 'In stock' : 'Out of stock',
            ];
        }, $productMatches), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $instructions = <<<PROMPT
You are the Essen Pharmacy AI Quick Help assistant for a Malaysian pharmacy e-commerce site.
Answer only general store, product availability, delivery, order-status, and payment-process questions.
Use the supplied customer order summary only when it is relevant. The live product catalogue matches are the only source of truth for product name, price, and availability. State prices in RM exactly as supplied. Never invent order details, stock, refunds, prices, policies, delivery dates, or product matches.
Do not diagnose conditions or give dosage, medication, allergy, pregnancy, interaction, or emergency advice. For those requests, briefly say that a pharmacist or doctor must advise them; for severe or urgent symptoms, tell them to seek urgent medical help.
Do not request passwords, card details, OTPs, or other sensitive information. Do not perform cancellations, refunds, address changes, or account changes; direct the customer to support.
Keep answers short, friendly, and practical. If a human needs to verify the request, say "Please send this to admin".

Customer active orders:
{$orderSummary}

Live product catalogue matches:
{$productSummary}
PROMPT;

    $payload = json_encode([
        'systemInstruction' => ['parts' => [['text' => $instructions]]],
        'contents' => [[
            'role' => 'user',
            'parts' => [['text' => $message]],
        ]],
        'generationConfig' => [
            'maxOutputTokens' => 300,
            'temperature' => 0.3,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        error_log('Support AI request failed: could not encode Gemini request payload.');
        return supportAiFallback('AI Quick Help is temporarily unavailable. Please send your message to our support team.');
    }

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($config['model'])
        . ':generateContent?key=' . rawurlencode($config['api_key']);
    $curl = curl_init($endpoint);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);

    $rawResponse = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($rawResponse === false || $statusCode < 200 || $statusCode >= 300) {
        $errorBody = is_string($rawResponse) ? json_decode($rawResponse, true) : [];
        $errorBody = is_array($errorBody) ? $errorBody : [];
        supportAiLogError($config, $statusCode, $curlError, $errorBody);
        return supportAiFallback(
            'AI Quick Help is temporarily unavailable. Please send your message to our support team.',
            $statusCode === 429
        );
    }

    $response = json_decode($rawResponse, true);
    $parts = $response['candidates'][0]['content']['parts'] ?? [];
    $replyParts = [];
    foreach ($parts as $part) {
        if (isset($part['text']) && is_string($part['text'])) {
            $replyParts[] = $part['text'];
        }
    }

    $reply = trim(implode("\n", $replyParts));
    if ($reply === '') {
        $finishReason = (string) ($response['candidates'][0]['finishReason'] ?? 'unknown');
        error_log('Support AI response contained no Gemini text: model=' . $config['model'] . ' finish_reason=' . $finishReason);
        return supportAiFallback('AI Quick Help could not prepare a reply. Please send your message to our support team.');
    }

    return ['ok' => true, 'message' => $reply];
}

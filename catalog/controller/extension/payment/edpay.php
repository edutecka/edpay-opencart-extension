<?php
/**
 * EDPay OpenCart Payment Controller
 */
class ControllerExtensionPaymentEdpay extends Controller {
    public function index() {
        $this->load->language('extension/payment/edpay');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['action'] = $this->url->link('extension/payment/edpay/checkout', '', true);

        return $this->load->view('extension/payment/edpay', $data);
    }

    public function checkout() {
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        if (!$order_info) {
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $publicKey = trim((string) $this->config->get('payment_edpay_public_key'));
        $privateKey = trim((string) $this->config->get('payment_edpay_private_key'));
        $publicMode = preg_match('/^pk_(test|live)_[a-fA-F0-9]{24}$/D', $publicKey, $publicMatch) ? $publicMatch[1] : null;
        $privateMode = preg_match('/^sk_(test|live)_[a-fA-F0-9]{64}$/D', $privateKey, $privateMatch) ? $privateMatch[1] : null;
        if ($publicMode === null || $privateMode === null || $publicMode !== $privateMode) {
            $this->response->redirect($this->url->link('checkout/checkout', 'error=edpay_failed', true));
            return;
        }
        $apiUrl = rtrim($this->config->get('payment_edpay_api_url') ?: 'https://api-pay.edutecka.com', '/');

        $orderId = $order_info['order_id'];
        $amountCentavos = (int) round($order_info['total'] * 100);

        $headers = [
            'Content-Type: application/json',
            "X-EDPay-Public-Key: {$publicKey}",
            "X-EDPay-Private-Key: {$privateKey}",
        ];
        $createLink = function ($url, $body, $idempotencyKey) use ($headers) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
                CURLOPT_HTTPHEADER => array_merge($headers, ["Idempotency-Key: {$idempotencyKey}"]),
                CURLOPT_POSTFIELDS => json_encode($body),
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return [$httpCode, json_decode((string) $response, true)];
        };
        $prefix = 'opencart-order-' . $orderId;
        [$httpCode, $link] = $createLink("{$apiUrl}/v1/payment-links", [
            'amount_minor' => $amountCentavos,
            'currency' => $order_info['currency_code'] ?: 'MZN',
        ], "{$prefix}-link-create");
        $linkId = $link['data']['id'] ?? null;
        if ($httpCode >= 200 && $httpCode < 300 && is_string($linkId) && $linkId !== '') {
            $this->load->model('extension/payment/edpay');
            $this->model_extension_payment_edpay->saveOrderLink(
                (string) $linkId,
                (int) $orderId,
                $amountCentavos,
                (string) ($order_info['currency_code'] ?: 'MZN')
            );
            [$sessionCode, $session] = $createLink("{$apiUrl}/v1/checkout/sessions", [
                'payment_link_id' => $linkId,
            ], "{$prefix}-checkout-session");
            $token = $session['data']['token'] ?? null;
            if ($sessionCode >= 200 && $sessionCode < 300 && is_string($token) && $token !== '') {
                $checkoutUrl = 'https://pay.edutecka.com/pay?' . http_build_query([
                    'token' => $token, 'pk' => $publicKey,
                ], '', '&', PHP_QUERY_RFC3986);
                $this->response->redirect($checkoutUrl);
                return;
            }
        }

        $this->response->redirect($this->url->link('checkout/checkout', 'error=edpay_failed', true));
    }

    public function webhook() {
        $this->load->language('extension/payment/edpay');
        $payload = file_get_contents('php://input');
        $signatureHeader = $this->request->server['HTTP_PAYMENT_SIGNATURE'] ?? '';
        $secret = trim((string) $this->config->get('payment_edpay_webhook_secret'));
        $toleranceConfig = $this->config->get('payment_edpay_webhook_tolerance_seconds');
        $tolerance = filter_var($toleranceConfig === null || $toleranceConfig === '' ? 300 : $toleranceConfig, FILTER_VALIDATE_INT);
        $tolerance = $tolerance !== false && $tolerance >= 0 ? $tolerance : 300;
        $signatureFields = [];
        foreach (explode(',', $signatureHeader) as $field) {
            $parts = explode('=', trim($field), 2);
            if (count($parts) === 2 && in_array($parts[0], ['t', 'v1'], true)) {
                if (array_key_exists($parts[0], $signatureFields)) {
                    $signatureFields[$parts[0]] = null;
                    break;
                }
                $signatureFields[$parts[0]] = trim($parts[1]);
            }
        }
        $timestamp = $signatureFields['t'] ?? '';
        $signature = $signatureFields['v1'] ?? '';
        $signedAt = preg_match('/^\d+$/', $timestamp) ? filter_var($timestamp, FILTER_VALIDATE_INT) : false;
        $validHeader = $signedAt !== false && preg_match('/^[a-f0-9]{64}$/i', $signature);
        $validTimestamp = $validHeader && abs(time() - $signedAt) <= $tolerance;
        $expected = $validTimestamp ? hash_hmac('sha256', $timestamp . '.' . $payload, $secret) : '';

        if ($payload === '' || $secret === '' || $signatureHeader === '' || !$validTimestamp
            || !hash_equals($expected, strtolower($signature))) {
            $this->response->addHeader('HTTP/1.1 401 Unauthorized');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(['error' => 'Invalid webhook signature']));
            return;
        }

        $event = json_decode($payload, true);
        if ($event && ($event['type'] ?? '') === 'payment.succeeded') {
            $data = $event['data'] ?? [];
            $linkId = (string) ($data['payment_link_id'] ?? '');
            $paymentId = (string) ($data['id'] ?? '');
            $this->load->model('extension/payment/edpay');
            $mapping = $linkId !== '' ? $this->model_extension_payment_edpay->getOrderLink($linkId) : [];
            if (!$mapping || ($data['status'] ?? '') !== 'succeeded' || $paymentId === ''
                || !isset($data['amount_minor']) || (int) $data['amount_minor'] !== (int) $mapping['amount_minor']
                || strtoupper((string) ($data['currency'] ?? '')) !== strtoupper((string) $mapping['currency'])) {
                $this->response->addHeader('HTTP/1.1 409 Conflict');
                $this->response->setOutput(json_encode(['error' => 'Payment link, status, amount, or currency mismatch']));
                return;
            }
            if (!empty($mapping['payment_id'])) {
                $this->response->addHeader('HTTP/1.1 409 Conflict');
                $this->response->setOutput(json_encode(['error' => 'Payment link already processed']));
                return;
            }
            $orderId = (int) $mapping['order_id'];
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($orderId);
            if (!$order_info || (int) round((float) $order_info['total'] * 100) !== (int) $data['amount_minor']
                || strtoupper((string) $order_info['currency_code']) !== strtoupper((string) $data['currency'])
                || (int) $order_info['order_status_id'] === (int) ($this->config->get('payment_edpay_order_status_id') ?: 2)) {
                $this->response->addHeader('HTTP/1.1 409 Conflict');
                $this->response->setOutput(json_encode(['error' => 'Mapped order amount or currency mismatch']));
                return;
            }
            if (!$this->model_extension_payment_edpay->claimOrderLink($linkId, $paymentId)) {
                $this->response->addHeader('HTTP/1.1 409 Conflict');
                $this->response->setOutput(json_encode(['error' => 'Payment link already processed']));
                return;
            }
            if ($orderId > 0) {
                $orderStatusId = $this->config->get('payment_edpay_order_status_id') ?: 2; // Processing
                $this->model_checkout_order->addOrderHistory($orderId, $orderStatusId, $this->language->get('text_payment_confirmed'), true);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['status' => 'success']));
    }
}

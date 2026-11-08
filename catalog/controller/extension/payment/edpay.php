<?php
/**
 * EDPay OpenCart Payment Controller
 */
class ControllerExtensionPaymentEdpay extends Controller {
    public function index() {
        $data['button_confirm'] = 'Pagar com EDPay';
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

        $publicKey = $this->config->get('payment_edpay_public_key');
        $privateKey = $this->config->get('payment_edpay_private_key');
        $apiUrl = rtrim($this->config->get('payment_edpay_api_url') ?: 'https://api-pay.edutecka.com', '/');

        $orderId = $order_info['order_id'];
        $amountCentavos = (int) round($order_info['total'] * 100);

        $payload = [
            'title' => "Pedido OpenCart #{$orderId}",
            'description' => "Pagamento da Encomenda #{$orderId}",
            'amount' => $amountCentavos,
            'currency' => $order_info['currency_code'] ?: 'MZN',
            'metadata' => [
                'opencart_order_id' => (string) $orderId,
                'email' => $order_info['email'],
            ],
            'success_url' => $this->url->link('checkout/success', '', true),
            'cancel_url' => $this->url->link('checkout/checkout', '', true),
        ];

        $ch = curl_init("{$apiUrl}/v1/core/payment-links");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "X-EDPay-Public-Key: {$publicKey}",
                "X-EDPay-Private-Key: {$privateKey}",
                "Idempotency-Key: oc-{$orderId}-" . time(),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $result = json_decode((string) $response, true);
            $checkoutUrl = $result['data']['hosted_url'] ?? $result['data']['checkout_url'] ?? null;
            if ($checkoutUrl) {
                $this->response->redirect($checkoutUrl);
                return;
            }
        }

        $this->response->redirect($this->url->link('checkout/checkout', 'error=edpay_failed', true));
    }

    public function webhook() {
        $payload = file_get_contents('php://input');
        $signature = $this->request->server['HTTP_X_EDPAY_SIGNATURE'] ?? '';
        $secret = $this->config->get('payment_edpay_webhook_secret');

        if ($secret) {
            $expected = hash_hmac('sha256', $payload, $secret);
            if (!hash_equals($expected, $signature)) {
                $this->response->addHeader('HTTP/1.1 401 Unauthorized');
                $this->response->setOutput(json_encode(['error' => 'Assinatura inválida']));
                return;
            }
        }

        $event = json_decode($payload, true);
        if ($event && ($event['type'] ?? '') === 'payment.succeeded') {
            $orderId = (int) ($event['data']['metadata']['opencart_order_id'] ?? 0);
            if ($orderId > 0) {
                $this->load->model('checkout/order');
                $orderStatusId = $this->config->get('payment_edpay_order_status_id') ?: 2; // Processing
                $this->model_checkout_order->addOrderHistory($orderId, $orderStatusId, 'Pagamento EDPay confirmado com sucesso.', true);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['status' => 'success']));
    }
}

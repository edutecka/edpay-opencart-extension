<?php
/**
 * EDPay OpenCart Payment Model
 */
class ModelExtensionPaymentEdpay extends Model {
    private function ensureOrderLinkTable(): void {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "edpay_order_link` (
            `payment_link_id` varchar(100) NOT NULL,
            `order_id` int NOT NULL,
            `amount_minor` bigint unsigned NOT NULL,
            `currency` char(3) NOT NULL,
            `payment_id` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`payment_link_id`), UNIQUE KEY `payment_id` (`payment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function saveOrderLink(string $linkId, int $orderId, int $amountMinor, string $currency): void {
        $this->ensureOrderLinkTable();
        $this->db->query("INSERT INTO `" . DB_PREFIX . "edpay_order_link` SET payment_link_id = '" . $this->db->escape($linkId) . "', order_id = '" . (int) $orderId . "', amount_minor = '" . (int) $amountMinor . "', currency = '" . $this->db->escape($currency) . "' ON DUPLICATE KEY UPDATE order_id = VALUES(order_id), amount_minor = VALUES(amount_minor), currency = VALUES(currency)");
    }

    public function getOrderLink(string $linkId): array {
        $this->ensureOrderLinkTable();
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "edpay_order_link` WHERE payment_link_id = '" . $this->db->escape($linkId) . "' LIMIT 1");
        return $query->row;
    }

    public function claimOrderLink(string $linkId, string $paymentId): bool {
        $this->db->query("UPDATE `" . DB_PREFIX . "edpay_order_link` SET payment_id = '" . $this->db->escape($paymentId) . "' WHERE payment_link_id = '" . $this->db->escape($linkId) . "' AND payment_id IS NULL");
        return $this->db->countAffected() === 1;
    }

    public function getMethod($address, $total) {
        $this->load->language('extension/payment/edpay');

        $status = true;
        if ($this->config->get('payment_edpay_geo_zone_id')) {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_edpay_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
            if (!$query->num_rows) {
                $status = false;
            }
        }

        $method_data = [];
        if ($status) {
            $method_data = [
                'code'       => 'edpay',
                'title'      => $this->config->get('payment_edpay_title') ?: $this->language->get('text_title'),
                'terms'      => '',
                'sort_order' => $this->config->get('payment_edpay_sort_order')
            ];
        }

        return $method_data;
    }
}

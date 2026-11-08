<?php
/**
 * EDPay OpenCart Payment Model
 */
class ModelExtensionPaymentEdpay extends Model {
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
                'title'      => $this->config->get('payment_edpay_title') ?: 'EDPay (M-Pesa, e-Mola, Cartão, Banco)',
                'terms'      => '',
                'sort_order' => $this->config->get('payment_edpay_sort_order')
            ];
        }

        return $method_data;
    }
}

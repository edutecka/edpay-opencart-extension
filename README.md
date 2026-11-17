# EDPay for OpenCart — Official Development Preview

**Version:** 1.6.0

This repository is an implementation preview, not a production release. **It is not installable as a complete OpenCart extension in its current state.** The repository contains a catalog payment controller and model, plus an English catalog language file; it does not include the admin configuration screens, catalog view, or an OpenCart installer package. Compatibility with specific OpenCart versions has not been validated.

Do not use this code for live transactions. The implementation also has webhook-verification and retry limitations described below.

## Requirements and intended integration

The source uses PHP cURL, JSON, and HMAC functions. Use it only in an isolated OpenCart development store with an EDPay account and an explicitly approved sandbox API endpoint.

The included model exposes an `edpay` payment method. Its controller creates a payment parent, attaches hosted checkout to that same payment, and redirects the customer using the returned opaque token. The hosted checkout uses the payment methods enabled for the merchant; the extension does not select or require a specific gateway.

## Source checkout

```bash
git clone https://github.com/edutecka/edpay-opencart-extension.git
cd edpay-opencart-extension
```

Review `catalog/controller/extension/payment/edpay.php` and `catalog/model/extension/payment/edpay.php`. Do not copy this repository into a live store or expect to install/enable it from the OpenCart administration panel: the required admin and view files are not present, and the controller's `index()` action loads a view that is not included. A developer completing the preview must add those OpenCart components and test the result on a disposable store before attempting checkout.

## Current checkout behavior

The catalog controller expects an existing OpenCart order and reads these settings from OpenCart configuration:

- `payment_edpay_public_key` and `payment_edpay_private_key`
- `payment_edpay_api_url`
- `payment_edpay_webhook_secret`
- Optional `payment_edpay_webhook_tolerance_seconds` (defaults to `300`)
- `payment_edpay_order_status_id`
- `payment_edpay_title`, `payment_edpay_sort_order`, and optionally `payment_edpay_geo_zone_id`

There is no included admin screen to enter or manage these settings. The controller creates one parent payment with `POST /v1/payments`, then starts hosted checkout for it with `POST /v1/payments/{id}/public-checkout`, using stable order-based `Idempotency-Key` values. It builds the hosted URL from the returned opaque token using the documented frontend URL `https://pay.edutecka.com/pay?token=...&pk=...`.

Partner API requests require both keys together: pair `pk_test_...` with `sk_test_...`, or `pk_live_...` with `sk_live_...`. The controller rejects missing, malformed, or cross-environment pairs before sending a request.

The controller defaults the API base URL to `https://api-pay.edutecka.com`; configure `payment_edpay_api_url` to the correct authorized environment before testing. Link and session requests use stable order-based idempotency keys.

## Webhook route and security limitations

The callback route implemented by the catalog controller is:

```text
https://store.example/index.php?route=extension/payment/edpay/webhook
```

The extension stores payment reference, order ID, amount, and currency in its own prefixed table. The webhook requires the matching reference, `type=payment.succeeded`, `status=succeeded`, and matching amount/currency; a compare-and-set claim rejects duplicate settlements. Signature verification requires `Payment-Signature` and the configured secret, with a configurable 300-second default timestamp window.

Always confirm payment from the verified server-side webhook, not from the customer's browser redirect.

## Documentation

- [OpenCart integration guide](https://docs-pay.edutecka.com/plugins/opencart/)
- [Payments API](https://docs-pay.edutecka.com/api/payments/)
- [Webhooks](https://docs-pay.edutecka.com/api/webhooks/)
- [API authentication](https://docs-pay.edutecka.com/guides/authentication/)

## License

MIT.

## Other official SDK and platform repositories

These links identify official project repositories; a repository listing does not imply a published package or production-ready integration. Review each project's status and requirements before use.

### SDKs

- [JavaScript / TypeScript SDK](https://github.com/edutecka/edpay-javascript-sdk)
- [Python SDK](https://github.com/edutecka/edpay-python-sdk)
- [PHP SDK](https://github.com/edutecka/edpay-php-sdk)
- [Java SDK](https://github.com/edutecka/edpay-java-sdk)
- [.NET SDK](https://github.com/edutecka/edpay-dot-net-sdk)

### E-commerce and billing integrations

- [WooCommerce (WordPress)](https://github.com/edutecka/edpay-woocommerce-plugin)
- [Shopify](https://github.com/edutecka/edpay-shopify-app)
- [Magento / Adobe Commerce](https://github.com/edutecka/edpay-magento2-module)
- [PrestaShop](https://github.com/edutecka/edpay-prestashop-module)
- [WHMCS](https://github.com/edutecka/edpay-whmcs-gateway)

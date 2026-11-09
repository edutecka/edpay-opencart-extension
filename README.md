# EDPay for OpenCart — Official Development Preview

This repository is an implementation preview, not a production release. **It is not installable as a complete OpenCart extension in its current state.** The repository currently contains only the catalog payment controller and model; it does not include the admin configuration screens, catalog view and language files, or an OpenCart installer package. Compatibility with specific OpenCart versions has not been validated.

Do not use this code for live transactions. The implementation also has webhook-verification and retry limitations described below.

## Requirements and intended integration

The source uses PHP cURL, JSON, and HMAC functions. Use it only in an isolated OpenCart development store with an EDPay account and an explicitly approved sandbox API endpoint.

The included model exposes an `edpay` payment method. Its controller is intended to create a hosted payment link for the current OpenCart order and redirect the customer to the URL returned by EDPay. The hosted checkout uses the payment methods enabled for the merchant; the extension does not select or require a specific gateway.

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
- `payment_edpay_order_status_id`
- `payment_edpay_title`, `payment_edpay_sort_order`, and optionally `payment_edpay_geo_zone_id`

There is no included admin screen to enter or manage these settings. If a developer supplies the missing configuration and UI, the controller posts a payment-link request to `/v1/core/payment-links`, sends `X-EDPay-Public-Key` and `X-EDPay-Private-Key`, and redirects to `data.hosted_url` or `data.checkout_url` in a successful response. The request includes the order total converted to minor currency units, the order currency (or `MZN` if absent), order metadata, and success/cancel URLs.

The controller currently defaults the API base URL to `https://api-pay.edutecka.com`. Set `payment_edpay_api_url` explicitly to the sandbox endpoint provided by EDPay before any test; do not rely on the default. Its idempotency key includes the current time, so separate attempts for one order receive different keys and are not reliably deduplicated.

## Webhook route and security limitations

The callback route implemented by the catalog controller is:

```text
https://store.example/index.php?route=extension/payment/edpay/webhook
```

The controller handles `payment.succeeded` events and applies the configured order status (or OpenCart status ID `2` when unset). It reads the raw body and `X-EDPay-Signature` and compares the signature with an HMAC-SHA256 hex digest when a webhook secret is configured.

**Important:** the current code only checks the signature when `payment_edpay_webhook_secret` is non-empty. If it is missing, the controller accepts the callback without signature verification. It also has no event-deduplication logic. Do not expose this callback or use it for live payments until the implementation is changed to require signature verification and safely handle repeated events. Always confirm payment from a verified server-side webhook, not from the customer's browser redirect.

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

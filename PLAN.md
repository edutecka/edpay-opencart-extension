# Roadmap and Architecture Plan - EDPay OpenCart Extension

## Development Phases

### Phase 1: Catalog Integration Scaffold (Complete)
- [x] Add the catalog payment controller and model.

### Phase 2: Configuration, Checkout, and Callback (In Progress)
- [ ] Add the missing admin configuration screens and catalog view.
- [ ] Complete hosted payment-link integration through `/v1/core/payment-links`.
- [ ] Complete the IPN/webhook callback at `index.php?route=extension/payment/edpay/webhook`.

### Phase 3: OCMOD Packaging
- [ ] Build an `.ocmod.zip` package compatible with the OpenCart 3.x and 4.x installers.
- [ ] Publish to the OpenCart Marketplace.

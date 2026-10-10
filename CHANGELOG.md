# Changelog

All notable changes to this project are documented here. This project follows
[Semantic Versioning](https://semver.org/).

## [2.0.0] - 2026-10-10
### Changed (breaking)
- **Payment rules are configured in the admin** (Sales > Softcode Checkout > Payment
  Rules). The hard-coded privat/cvr/ean → ePay/purchase-order defaults are removed; with
  no rules every available payment method is allowed. The `allowedMethods` DI argument
  still works as a fallback.
- **The order confirmation template is no longer forced** on the whole store; select it
  under Sales Emails if wanted.
- Reference template field ids renamed to `#sc-address-*` so shipping modules can read them.
### Added
- Admin configuration section with skip-cart-page, address country, payment rules,
  Reply-To and payment-failed email settings.
- Alternative delivery address: company and receiver go into the shipping address
  (receiver split into first/last name); `main_*`/`alt_*` fields are copied to the order.
- Newsletter opt-in on place order (`newsletter=1`), which never fails the order.
- Failed orders are logged with a reference that is shown to the customer.
- Reply-To plugin for order-lifecycle emails.
- Plugin that logs failed payments instead of emailing them (bots abuse that email for
  template injection); can be switched back on.
- `sections.xml` so the minicart refreshes after placing an order or applying a coupon.
- EAN numbers must be 13 digits.
- Delivery mount `#sc-delivery-methods` and newsletter/alternative-address fields in the
  reference template.
- CI runs the unit tests on PHP 8.1–8.4.

## Earlier unreleased changes, included in 2.0.0
### Added
- **Unit tests now run in CI** as a real gate — a standalone `Test/bootstrap.php`
  autoloads the module and stubs the mocked Magento contracts, so both unit suites
  run via `phpunit -c phpunit.xml.dist` without a Magento install.
- `Test/Unit/Observer/ValidateAndMapQuoteToOrderTest` — unit tests for the final
  submit gate (missing buyer type / CVR / EAN / method and a method not allowed for
  the buyer type are rejected; a valid combination is mapped onto the order).
- A concrete integration test plan and a manual smoke-test checklist covering the
  full quote → order flow, guest-order behaviour and quote-to-order mapping
  (`Test/README.md`).

## [1.0.0]
### Added
- Central `PaymentPolicy` shared by the payment controllers and the submit
  observer, so buyer-type payment rules have a single source of truth.
- Form-key (CSRF) validation on every POST endpoint.
- Unit tests for the payment policy.
- `composer.json`, CI (PHP lint + Magento coding standard), MIT license.

### Changed
- Controllers rewritten to declare their HTTP verb and use constructor DI
  (no deprecated `Action` base class, no ObjectManager).
- Cart totals now come from Magento's own quote collectors.
- Exceptions are logged server-side; only safe messages reach the frontend.

### Removed
- Course-specific ("kursist") logic and the hidden dependency on the GLS module.

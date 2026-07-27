# Changelog

All notable changes to this project are documented here. This project follows
[Semantic Versioning](https://semver.org/).

## [Unreleased]
### Added
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

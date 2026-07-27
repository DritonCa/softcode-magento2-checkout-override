# Changelog

All notable changes to this project are documented here. This project follows
[Semantic Versioning](https://semver.org/).

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

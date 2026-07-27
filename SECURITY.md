# Security Policy

## Reporting a vulnerability
Please report security issues privately by email to the maintainer rather than
opening a public issue. You will get an acknowledgement within a few working days.

## Notes for integrators
- All state-changing endpoints validate Magento's form key (`CsrfAwareActionInterface`).
- Internal exception details are logged server-side and never returned to the browser.
- Smoke-test the checkout flow after installation; the frontend template/JS is a
  reference implementation and is not covered by automated browser tests.

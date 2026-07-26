# Softcode_CheckoutOverride

A Magento 2 module that replaces the default checkout with a custom, server-driven
**one-page checkout**. It gives you a clean foundation for shops that need strict
business rules the default checkout can't express — for example separate flows for
private customers and companies (EAN / CVR), or placing the order straight into an ERP.

> This is a **foundation to build on**, not a drop-in finished checkout. It solves
> the hard part — replacing Magento's checkout safely and server-side — and leaves
> the presentation open for you to shape.

---

## Requirements

- Magento **2.4.x**
- PHP **8.1** or **8.2**

## Installation

**With Composer (recommended)**

```bash
composer require softcode/module-checkout-override
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

**Manually**

Copy the module to `app/code/Softcode/CheckoutOverride`, then run the same three
Magento commands above.

Verify it is active:

```bash
bin/magento module:status Softcode_CheckoutOverride
```

---

## How it works

The module takes over the checkout in three layers:

1. **Layout** — `checkout_index_index.xml` removes Magento's default checkout
   component and renders this module's `checkout.phtml` instead.
2. **Frontend (JS)** — `checkout.js` / `cart.js` drive the flow with small AJAX
   calls to the module's own controllers (address, payment, coupon, place order).
3. **Server (controllers + observer)** — thin controllers accept each step and a
   `ValidateAndMapQuoteToOrder` observer enforces the business rules before the
   order is created.

```
Browser (checkout.js)
   │  AJAX
   ▼
Softcode controllers ──▶ Quote ──(submit)──▶ ValidateAndMapQuoteToOrder ──▶ Order
```

### Endpoints

| Method | Route | Purpose |
| --- | --- | --- |
| `GET`  | `/softcode/cart/index` | Current cart contents |
| `POST` | `/softcode/cart/applyCoupon` | Apply a discount code |
| `POST` | `/softcode/index/index` | Save customer type (private / company) |
| `GET`  | `/softcode/index/paymentMethods` | Available payment methods |
| `POST` | `/softcode/index/saveAddress` | Save the address to the quote |
| `POST` | `/softcode/index/savePayment` | Save the chosen payment method |
| `POST` | `/softcode/index/placeOrder` | Place the order |

Each controller declares its HTTP verb via `HttpGetActionInterface` /
`HttpPostActionInterface`, per Magento conventions.

---

## Known limitations

- **CSRF on the AJAX POST endpoints.** The frontend currently posts without a
  `form_key`, so on a stock Magento the POST steps are subject to CSRF rejection.
  Before production, send Magento's `form_key` with each POST **and** validate it in
  the controller (implement `CsrfAwareActionInterface` backed by
  `Magento\Framework\Data\Form\FormKey\Validator`). This is the recommended,
  by-the-book fix and is intentionally left to the integrator so it can be tested
  against a real checkout.
- The template is deliberately minimal — style and copy are yours to own.

---

## License

MIT — see [LICENSE](LICENSE).

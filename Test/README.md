# Tests

The checkout's business rules live in one place (`Model\Payment\PaymentPolicy`) and
are enforced by the controllers *and* the order-submit observer. The tests are
layered accordingly: fast unit tests for the rules and the enforcement gate, and a
specified integration suite plus a manual smoke checklist for the full flow.

## Unit tests (automated)

Standard Magento 2 unit tests — no database, run against a Magento install:

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist \
  app/code/Softcode/CheckoutOverride/Test/Unit
```

| Test | What it pins |
| --- | --- |
| `Test/Unit/Model/Payment/PaymentPolicyTest` | The buyer-type → payment-method rules: privat = ePay; cvr/ean = ePay or purchase order; unknown buyer type / method rejected; ruleset is di-configurable. |
| `Test/Unit/Observer/ValidateAndMapQuoteToOrderTest` | The final submit gate: missing buyer type, missing CVR/EAN number, missing method and a method not allowed for the buyer type are all rejected; a valid combination copies the `company_*` fields onto the order. |

## Integration tests (specified — run in a Magento integration environment)

Full quote → order coverage needs a database, so it belongs in Magento's
integration framework rather than in CI. The suite below is specified concretely so
it can be dropped into `dev/tests/integration` (or shipped under
`Test/Integration`) and run with:

```bash
vendor/bin/phpunit -c dev/tests/integration/phpunit.xml.dist \
  app/code/Softcode/CheckoutOverride/Test/Integration
```

**`Test/Integration/PlaceOrderFlowTest.php`**

| Test method | Scenario | Asserts |
| --- | --- | --- |
| `testPlacesOrderForAllowedCombination` (dataProvider `allowedCombinations`) | privat+epay, cvr+epay, cvr+purchaseorder, ean+epay, ean+purchaseorder | order is created; `getStatus()` is not cancelled; grand total matches the quote |
| `testRejectsMethodNotAllowedForBuyerType` | privat + purchaseorder | `submit()` throws `LocalizedException`; **no** order row is created |
| `testRejectsUnknownBuyerType` | buyer type `foo` + epay | throws `LocalizedException` |
| `testRequiresCvrNumberForCvrBuyer` | cvr, blank `company_cvr` | throws `LocalizedException` |
| `testRequiresEanNumberForEanBuyer` | ean, blank `company_ean` | throws `LocalizedException` |
| `testMapsBuyerTypeFieldsOntoOrder` | cvr + purchaseorder + company data | order carries `company_type`, `company_name`, `company_cvr` copied from the quote |
| `testOrdersArePlacedAsGuest` | any allowed combination | `order.getCustomerIsGuest() === true` and `order.getCustomerId() === null` (guest checkout by design) |

Fixtures: `@magentoDataFixture` a simple product plus an active quote with a shipping
and billing address; set `company_type`/`company_cvr`/`company_ean` on the quote and
the payment method, then call `QuoteManagement::submit($quote)`. The observer bound
to `sales_model_service_quote_submit_before` runs inside `submit()`, so these tests
exercise the real enforcement path, not a mock.

## Manual smoke test (frontend reference implementation)

The frontend template/JS is a deliberately small reference; verify it by hand after
install (it is not covered by browser tests):

1. Add a product to the cart and open `/softcode/cart/index` — items and totals show.
2. `/softcode/index/index` — choose **privat**; only **ePay** is offered as payment.
3. Choose **cvr**, enter a CVR number; **ePay** and **purchase order** are offered.
4. Try to submit **privat + purchase order** by tampering with the request — the
   server rejects it with a generic message (the policy gate).
5. Place a valid order — you land on the success page; with ePay you are sent to the
   ePay window; the order exists in admin as a **guest** order with the buyer-type
   fields set.

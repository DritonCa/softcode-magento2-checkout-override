# Tests

Unit tests are standard Magento 2 unit tests. Run them against a Magento install:

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist \
  app/code/Softcode/CheckoutOverride/Test/Unit
```

`PaymentPolicyTest` is the executable specification of the buyer-type payment
rules (privat / cvr / ean × ePay / purchase order).

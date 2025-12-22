# Softcode Custom Checkout Override for Magento 2

Free, lightweight Magento 2 module that provides a **solid starting point**
for building a **fully custom checkout** with **strict business rules**.

This module exists because **Magento does not provide a clean, documented way
to replace the default checkout**, and most developers struggle to find a
reliable foundation to build on.

---

## 🎯 Purpose of this module

`Softcode_CheckoutOverride` is **not a finished checkout solution**.

It is a **developer-friendly foundation** designed to be:

- Extended
- Customized
- Refactored
- Built upon

Its main goal is to solve the **hardest part first**:
> _Replacing Magento’s checkout in a safe, predictable, server-side way._

---

## ✨ Features

- 🧱 **Custom checkout foundation**
- 🧾 **Company type handling**
    - Privat
    - CVR
    - EAN
- 💳 **Strict payment rules (server-side enforced)**
  | Company type | Allowed payment methods |
  |-------------|------------------------|
  | Privat      | ePay |
  | CVR         | ePay |
  | EAN         | ePay + Invoice |
- 🔐 **No frontend-only validation**
- 🧠 **Quote → Order data mapping**
- 🔁 **Alternative delivery address support**
- 🧩 **Checkout-agnostic** (works with any UI)
- 🆓 **100% free & open source**

---

## 🧱 Supported use cases

✔ Developers building a **custom checkout from scratch**  
✔ Headless Magento projects  
✔ B2B / B2C hybrid shops  
✔ Public sector (EAN) flows

> This module intentionally avoids Magento Checkout JS internals.

---

## 🧠 What this module does (and does NOT do)

### ✅ Handles
- Checkout replacement architecture
- Company type persistence
- CVR / EAN validation
- Payment method enforcement
- Server-side order blocking
- Quote → Order data mapping

### ❌ Does NOT handle
- Shipping methods
- Carrier pricing
- ParcelShop logic
- Checkout UI / design

Shipping is expected to be handled by **separate modules** (e.g. `Softcode_Gls`).

---

## 🔐 Server-side validation

Before an order can be placed, the module ensures:

- Company type is selected
- Required CVR / EAN exists
- A payment method is selected
- Payment method is allowed for the company type

Invalid orders are blocked even if attempted via:
- REST API
- GraphQL
- Custom frontend
- Race conditions

---

## 🧾 Data stored on order

The following fields are persisted from quote → order:

- `company_type`
- `company_name`
- `company_cvr`
- `company_ean`
- Selected payment method
- Main address reference
- Optional alternative delivery address

This makes the module suitable for:
- Accounting
- ERP systems
- Further customization




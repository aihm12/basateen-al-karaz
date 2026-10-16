# Basateen Al Karaz REST API

Base URL: `http://127.0.0.1:8000/api`

Send `Accept: application/json` and `Content-Type: application/json` for JSON requests. Private routes require `Authorization: Bearer <token>`.

## Authentication

`POST /auth/send-otp`

```json
{"phone":"+966500000001"}
```

Returns 202 with `expires_in: 300`. In local/testing only, `development_code` contains the random code. No SMS is sent. Production must bind a real implementation of `App\Contracts\OtpSender`; the local sender fails closed with 503.

`POST /auth/verify-otp`

```json
{"phone":"+966500000001","code":"<development_code>","name":"اسم العميل"}
```

Returns `token`, `token_type: Bearer`, `user`. A customer is created on first successful verification; email is optional. Token expires in 30 days. Codes are hashed, expire in five minutes, and cannot be replayed. Five bad attempts exhaust a challenge. Sending is limited to one request per phone per minute and 20 per day, plus IP limits. Verification has phone and IP limits.

`POST /auth/logout` revokes the current token (204). `GET /me` returns the current customer. Disabled customers cannot use private routes.

## Routes

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | /categories | No | Active categories |
| GET | /products | No | Paginated catalog; category_id, search, per_page (1–50) |
| GET | /products/{id} | No | Product, images, active variants, options |
| GET | /branches | No | Active branches and delivery zones |
| GET | /delivery-slots?branch_id=1&date=YYYY-MM-DD | No | Future available slots within 30 days |
| POST | /auth/send-otp | No | Request OTP |
| POST | /auth/verify-otp | No | Verify and obtain token |
| POST | /auth/logout | Yes | Revoke token |
| GET | /me | Yes | Current customer |
| GET / POST | /addresses | Yes | List/create own addresses |
| GET | /favorites | Yes | Own favorite products |
| PUT / DELETE | /favorites/{product_id} | Yes | Add/remove favorite |
| GET / DELETE | /cart | Yes | View/clear cart |
| POST | /cart/items | Yes | Add configured variant |
| PATCH / DELETE | /cart/items/{id} | Yes | Change quantity/remove own item |
| GET / POST | /orders | Yes | Own order history/create order |
| GET | /orders/{id} | Yes | Own order details |

## Cart

```json
{
  "branch_id": 1,
  "variant_id": 1,
  "quantity": 2,
  "option_value_ids": [1]
}
```

Get IDs from the catalog instead of relying on these sample IDs. Mango requires one ripeness option, juice one sugar option, and fruit-box extras are optional. Quantity is an integer 1–99 (number of fixed-weight variants, not arbitrary kilograms). Client prices/totals are ignored. Duplicate or unrelated options and unavailable stock return 422. Clear an existing cart before changing branches. Repeated additions create separate configured lines; all quantities for the same variant count against stock.

`PATCH /cart/items/{id}` body: `{"quantity":3}`.

Cart response contains `data`, `subtotal`, `currency: SAR`. Cart prices are snapshots; checkout revalidates and reprices using the current catalog. Price-confirmation UI can be added before launch.

## Addresses and delivery

```json
{
  "label": "المنزل",
  "district": "حي تجريبي",
  "street": "شارع تجريبي",
  "delivery_zone_id": 1,
  "is_default": true
}
```

Optional fields: latitude, longitude, building_number, floor, apartment, instructions. A district must exactly match one listed in the selected active zone. This starter uses configured district names, not polygon/geocoding verification. No real service coverage has been configured.

Delivery fee/minimum/free-delivery threshold are zone settings, evaluated against the merchandise total after coupon discount. Slots are checked by branch/date/day/time and capacity in Asia/Riyadh, with row locking at checkout.

## Orders

`POST /orders` requires a unique `Idempotency-Key` header of 8–80 letters, digits, underscores or hyphens. Reuse the same key and payload for a network retry; a changed payload returns 409. A retry returns the original order with 201. The key is scoped to the customer.

Pickup:

```json
{"delivery_type":"pickup","payment_method":"cash","customer_notes":"طلب تجريبي"}
```

Delivery:

```json
{
  "delivery_type": "delivery",
  "payment_method": "cash",
  "address_id": 1,
  "delivery_date": "<a future YYYY-MM-DD>",
  "delivery_slot_id": 1,
  "coupon_code": "WELCOME20"
}
```

Cash is the only enabled payment method. New payment status is pending; no charge is performed. Amounts are decimal strings in SAR. Catalog/option prices include tax; order `tax` is informational included tax, not an additional charge. A coupon is allocated across line totals for tax calculation. Delivery fees currently do not have a separate tax component. Confirm business taxation settings before real operation.

Order items retain product/variant names, selected options and prices. Address and delivery slot snapshots preserve fulfillment details. Checkout is atomic: order, line items, payment record, delivery, notification, stock deduction, integration outbox and cart clear succeed or roll back together.

Foodics logs are `deferred`, with no credentials or external requests. Foodics adapter, reliable worker, retries, webhooks, provider mapping and reconciliation remain future work. Do not interpret the local log as successful Foodics submission.

## Responses and errors

Most resources use `{"data": ...}`. Product and order lists use Laravel pagination (`data`, `links`, `meta`-equivalent paginator fields). Authentication responses return their fields at the top level.

Status codes: 200 read/update, 201 create, 202 OTP accepted, 204 delete/logout, 401 unauthenticated, 403 disabled customer, 404 missing/private resource, 409 idempotency conflict, 422 validation/business rule, 429 throttled, 503 provider unavailable.

## Development commands

From `C:\Users\M\Documents\Codex\Basateen-Al-Karaz\backend`:

```powershell
& ..\runtime\php\php.exe artisan migrate --seed
& ..\runtime\php\php.exe artisan route:list --path=api
& ..\runtime\php\php.exe artisan test
& ..\runtime\php\php.exe ..\runtime\composer.phar install
```

Tests use only `basateen_al_karaz_test`, guarded by a dedicated bootstrap; `.env.testing` is local and ignored by Git. Use the bundled PHP because the global PHP is 7.4. Seeds are allowed only in local/testing and use firstOrCreate so rerunning them does not overwrite configured prices or add duplicates. Never use migrate:fresh against the application database once it contains real data.

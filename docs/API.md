# Tryino REST API (v1)

Mobile and SPA clients should call the **versioned** JSON API under:

`{APP_URL}/api/v1/...`

Replace `{APP_URL}` with your environment base (for local Laragon, often `http://tryino-ecom.test` or `http://localhost`).

## Conventions

### Success envelope

```json
{
  "success": true,
  "data": {},
  "meta": {
    "api": {
      "version": "1.0.0",
      "path": "api/v1"
    }
  }
}
```

List endpoints may add `meta.pagination` (current_page, last_page, per_page, total).

### Error envelope

```json
{
  "success": false,
  "message": "Human-readable message",
  "code": "optional_machine_code",
  "errors": {},
  "meta": {
    "api": {
      "version": "1.0.0",
      "path": "api/v1"
    }
  }
}
```

Validation failures use HTTP **422**, `code` **`validation_failed`**, and Laravel-style `errors` (field => messages).

### Authentication (Sanctum personal access token)

Protected routes expect:

`Authorization: Bearer {plainTextToken}`

Create tokens via `POST /api/v1/auth/register` or `POST /api/v1/auth/login`.

### Guest cart & checkout

For **unauthenticated** clients, send a stable UUID on every cart/checkout request:

`X-Guest-Cart-Token: 550e8400-e29b-41d4-a716-446655440000`

The server uses this to load or create a guest cart. Authenticated users do **not** need this header (the user’s cart is used).

---

## Authentication

### `POST /api/v1/auth/register`

**Body (JSON)**

| Field | Rules |
| --- | --- |
| name | required |
| email | required, unique |
| password | required, confirmed |
| password_confirmation | required |
| device_name | optional (default used: `mobile`) |

**Example response (201)**

```json
{
  "success": true,
  "data": {
    "token": "1|xxxxxxxx",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "Ayesha",
      "email": "ayesha@example.com",
      "role": "customer"
    }
  },
  "meta": { "api": { "version": "1.0.0", "path": "api/v1" } }
}
```

### `POST /api/v1/auth/login`

**Body**

| Field | Rules |
| --- | --- |
| email | required |
| password | required |
| device_name | optional |

**Example success (200)** — same `data` shape as register (without 201-only nuance).

**Example failure (401)**

```json
{
  "success": false,
  "message": "Invalid credentials.",
  "code": "auth_invalid",
  "meta": { "api": { "version": "1.0.0", "path": "api/v1" } }
}
```

### `GET /api/v1/auth/me` — requires Bearer

Returns `data` = `UserResource` object. (This replaces the older `GET /api/v1/user` discovery route.)

### `POST /api/v1/auth/logout` — requires Bearer

Revokes the **current** token. `data`: `{ "revoked": true }`.

### `POST /api/v1/auth/logout-all` — requires Bearer

Revokes **all** tokens for the user.

---

## Catalog

### `GET /api/v1/categories`

Root categories for navigation.

### `GET /api/v1/categories/{slug}/products`

Paginated products for a category. Supports the same **filter** query parameters as the storefront (see `CatalogQueryService` / `CatalogController`); response `meta` may include `filters` when applied.

### `GET /api/v1/products/{slug}`

Full product detail payload for a single slug.

---

## Cart

All routes accept optional Bearer and/or `X-Guest-Cart-Token` (UUID) as described above.

### `GET /api/v1/cart`

Current cart payload (lines, totals, etc.).

### `POST /api/v1/cart/items`

**Body**

| Field | Rules |
| --- | --- |
| product_variant_id | required, exists |
| size_label | required |
| quantity | required, 1–20 |
| bargain_phone | optional (price lock / bargain linkage) |

### `PATCH /api/v1/cart/items/{cartItem}`

**Body:** `quantity` (integer, min 1).

### `DELETE /api/v1/cart/items/{cartItem}`

Removes the line; returns updated cart.

---

## Checkout

### `GET /api/v1/checkout/payment-methods`

Enabled gateways with labels and fee hints.

### `POST /api/v1/checkout`

**Body**

| Field | Rules |
| --- | --- |
| full_name, phone, line1, city | required |
| area, postal_code, notes | optional |
| guest_email | required **only** for guests (no valid Bearer user) |
| payment_gateway | required, must be an enabled code (e.g. `cod`) |

**Example response (201)**

```json
{
  "success": true,
  "data": {
    "order_number": "TRN-XXXX",
    "grand_total": 13199.0,
    "payment_gateway": "cod",
    "payment_status": "pending",
    "order_status": "pending",
    "payment": {
      "redirect_url": null,
      "immediate_success": true,
      "meta": {}
    }
  },
  "meta": { "api": { "version": "1.0.0", "path": "api/v1" } }
}
```

Online gateways may populate `payment.redirect_url` for WebView flows.

---

## Orders (authenticated)

### `GET /api/v1/orders`

Query: `per_page` (max 50). Returns `data` as array of summary rows + `meta.pagination`.

### `GET /api/v1/orders/{order_number}`

Order detail including **shipment / tracking** fields prepared by `CustomerOrderQueryService` (same semantics as authenticated web order view).

---

## Guest order lookup (tracking)

### `POST /api/v1/orders/lookup`

**Body:** `order_number`, `email` (must match the order).

Returns a **tracking-oriented** payload (no sensitive extras). **404** if no match.

---

## Bargain sessions

Under `POST/GET /api/v1/bargain/...` (start session, poll status, send message, accept, decline). Optional Bearer user; guest sessions may supply identifiers as defined in `BargainController` and `StartBargainSessionRequest`.

---

## Notifications (authenticated)

Requires the `notifications` database table (migration included in the project).

### `GET /api/v1/notifications`

Paginated `data` rows: `id`, `type`, `data`, `read_at`, `created_at`.

### `POST /api/v1/notifications/{id}/read`

Marks one notification as read.

---

## Auth errors

Missing or invalid Bearer token on protected routes returns **401** with `code`: **`unauthenticated`**.

---

## Rate limiting

The `v1` group uses Laravel’s `throttle:api` middleware. The named limiter `api` is registered in `AppServiceProvider` (default: 120 requests per minute per IP or authenticated user id). Adjust for production as needed.

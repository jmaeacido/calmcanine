# Calm Canine

A marketing site and on-site shop for **Calm Canine** — organic pumpkin and peanut butter wellness treats with CBD isolate, crafted to support calm moments, gentle relief, and everyday balance for dogs of all ages.

**Repository:** [github.com/jmaeacido/calmcanine](https://github.com/jmaeacido/calmcanine)  
**Status:** Local development only — no production hosting yet  
**Shop:** [product](product) — full on-site cart and checkout at `/cart` and `/checkout`

## Overview

Calm Canine combines a static marketing landing page with a lightweight PHP order API. The homepage moves from an emotional hero into product education, science-backed context, serving guidance, lifestyle gallery, and a conversion-focused shop CTA — with a fully polished mobile experience and no frontend build step. Cart, checkout, optional customer accounts, and order confirmation run in the browser and talk to JSON endpoints backed by file storage.

## Features

### Hero
- Interactive **orbit showcase** — six lifestyle video cards (Quiet Time, Cozy Naps, Happy Walks, Balanced Days, Easy Evenings, Gentle Relief) arranged around the product pouch
- Hover-driven **calm journey** on desktop — videos play when the product stage is hovered or focused
- **Mobile carousel** — horizontal swipe with scroll-snap, dot indicators, and edge-fade affordance
- Product tilt, cursor glow, trust badges, and scroll cue

### About
- Split-layout product showcase with ingredient grid
- Photoshopped product imagery and benefit copy
- CTAs linked to the on-site product page (`/product`)
- Viewport-height section on desktop

### Benefits
- Four benefit cards aligned to the brand’s calm-wellness tone (Quiet Moments, Restful Comfort, Happy Movement, Everyday Balance)

### Endocannabinoid System (ECS)
- Viewport-height **“Secret Ingredient”** science section
- Custom **endocannabinoid-system.mp4** animation with poster fallback
- Scroll-driven content beats and in-view video playback

### Serving Guide
- Weight-based serving cards (small, medium, large dog) with custom PNG illustrations
- CBD dosage copy and veterinary compliance fine print

### Gallery
- **“Calm Looks Good on Them”** lifestyle grid with hover/touch video moments
- Featured product card with pouch and treat imagery
- Gallery stats footer and shop link

### Shop
- Conversion-focused closing section with ambient background, trust chips, and dual CTAs
- Product visual stage with 3D tilt (desktop), scroll parallax, and treat accent

### Product Page (`/product`)
- Extensionless URL served from `product/index.html`
- Image gallery with pouch and treat photography
- One-time or subscribe & save purchase options with quantity selector
- Adds to on-site cart via `cart-store.js`

### Cart & Checkout
- **`/cart`** — review line items, update quantities, order summary
- **`/checkout`** — contact, shipping, payment, and place order
- **`/order`** — order confirmation with summary and shipping details
- Cart persists in `localStorage`; CBD-restricted states blocked at checkout
- Optional **customer accounts** (`/account`) — register, sign in, save contact/shipping, view order history
- Optional **newsletter prompt** on register and checkout; skipped after a successful subscribe
- Guest checkout remains fully supported; contact, shipping, payment, and terms are still required for every order
- Live tax and totals from the quote API when a shipping state is selected
- Orders submitted to the PHP API; confirmation page loads order details by ID

### Order API
- **`POST /api/quote`** — normalize line items, compute subtotal, shipping, tax, and total
- **`POST /api/checkout/session`** — validate and price the cart, then create a Stripe-hosted Checkout Session
- **`POST /api/checkout/complete`** — verify successful Stripe payment before persisting the order, sending emails, and queuing fulfillment
- **`POST /api/stripe/webhook`** — signed Stripe events finalize checkout if the shopper never returns, and queue Subscribe & save renewals
- **`GET /api/orders/{id}`** — return a sanitized order for the confirmation page
- **`GET /api/orders/export`** — fulfillment queue export (JSON or CSV; optional bearer/key auth via `FULFILLMENT_EXPORT_KEY`)
- Orders saved under `data/orders/`; email jobs under `data/email-queue/`; fulfillment queue in `data/fulfillment/queue.jsonl`

### Admin (`/admin`)
- Password-protected session login at `/admin/login` (`ADMIN_PASSWORD` in `.env`)
- **`/admin`** — list orders (customer, items, total, fulfillment, email flags); search by ID or email
- **`/admin/order?id=`** — full order detail; update fulfillment status; mark confirmation/ops emails sent or re-queue
- Admin APIs under `/api/admin/*` require an authenticated PHP session (`credentials: same-origin`)
- **`/admin/emails`** — searchable mailing records; archives contact forms and audits every Brevo SMTP attempt

### Global
- Sticky header with mobile nav drawer (Escape / outside-click close)
- Account / Sign in in the primary nav (session-aware label)
- Scroll reveal animations via `IntersectionObserver`
- Safe-area inset support for notched devices
- `prefers-reduced-motion` respected across animations and video playback
- Favicon and apple-touch-icon across all pages

## Tech Stack

| Layer | Details |
|-------|---------|
| **HTML5** | Semantic, accessible markup |
| **CSS3** | Custom properties, grid/flex, clamp-based typography, scroll animations (~3,785 lines) |
| **Vanilla JavaScript** | No framework, no build step (~930 lines across page scripts + shared modules) |
| **PHP 8+** | Lightweight JSON API for quotes, orders, and customer accounts |
| **Apache** | Clean URLs and API routing via `.htaccess` (`mod_rewrite`) |
| **Google Fonts** | [Cormorant Garamond](https://fonts.google.com/specimen/Cormorant+Garamond) (brand display), [Montserrat](https://fonts.google.com/specimen/Montserrat) (Gotham stand-in), [Poppins](https://fonts.google.com/specimen/Poppins) (TT Norms stand-in) |
| **Brand palette** | Gold `#c6a262`, forest `#2a3f34`, paper `#eee7e7`, sage `#63735c`, sand `#e6c68e` |
| **Media** | Optimized PNG posters + MP4 loops for hero and gallery moments |

## Project Structure

```
calmcanine/
├── index.html              # Main landing page
├── .htaccess               # Clean URLs for pages and API routes
├── admin/
│   ├── index.html          # Orders list (served at /admin)
│   ├── admin.js
│   ├── login/
│   │   ├── index.html      # Admin password login
│   │   └── admin-login.js
│   └── order/
│       ├── index.html      # Order detail + actions
│       └── admin-order.js
├── admin-api.js            # Credentialed fetch client for admin APIs
├── account/
│   ├── index.html          # Account profile + orders (served at /account)
│   ├── account.js
│   ├── login/
│   │   ├── index.html
│   │   └── account-login.js
│   └── register/
│       ├── index.html
│       └── account-register.js
├── account-nav.js          # Header Sign in / Account label
├── newsletter.js           # Optional newsletter modal on register and checkout
├── api-client.js           # fetch wrapper for quote, order, and account endpoints
├── cart-store.js           # Shared cart, pricing, and order logic
├── product/
│   └── index.html          # Shop page (served at /product)
├── cart/
│   ├── index.html
│   └── cart.js
├── checkout/
│   ├── index.html
│   └── checkout.js
├── order/
│   ├── index.html
│   └── order.js
├── product.js              # Product page interactions
├── api/
│   ├── bootstrap.php       # Catalog, tax, quote, order, queue, admin helpers
│   ├── account-register.php
│   ├── account-login.php
│   ├── account-logout.php
│   ├── account-session.php
│   ├── account-update.php
│   ├── account-orders.php
│   ├── newsletter-status.php
│   ├── newsletter-subscribe.php
│   ├── mail.php            # Brevo SMTP sender, mailing archive, and templates
│   ├── stripe.php
│   ├── stripe-webhook.php  # POST /api/stripe/webhook
│   ├── checkout-session.php
│   ├── checkout-complete.php
│   ├── orders-create.php   # POST /api/orders/create
│   ├── orders-get.php      # GET /api/orders/{id}
│   ├── orders-export.php   # GET /api/orders/export
│   ├── admin-login.php     # POST /api/admin/login
│   ├── admin-logout.php    # POST /api/admin/logout
│   ├── admin-session.php   # GET /api/admin/session
│   ├── admin-orders-list.php
│   ├── admin-orders-get.php
│   ├── admin-orders-fulfillment.php
│   ├── admin-orders-email.php
│   └── config/
│       ├── catalog.json    # Product, shipping, restricted states
│       └── tax-rates.json  # State tax rates
├── data/                   # Runtime storage (gitignored except .htaccess)
│   ├── orders/             # One JSON file per order
│   ├── users/              # Customer accounts (hashed passwords)
│   ├── newsletter/         # Optional newsletter subscribers
│   ├── stripe/             # Webhook event, invoice, and subscription indexes
│   ├── email-queue/        # Queued confirmation / ops emails
│   └── fulfillment/        # queue.jsonl for export
├── scripts/
│   └── build-favicon.py    # Regenerate favicon PNG from SVG source
├── docs/
│   └── BRAND GUIDELINES FOR CALM CANINE.pdf
├── style.css               # Global styles, sections, responsive breakpoints
├── script.js               # Interactions (orbit, carousel, nav, video, parallax)
├── assets/
│   ├── calm-canine-pouch-v2.png   # Hero / shop product cutout
│   ├── calm-canine-pouch.png      # Original pouch asset
│   ├── product_1.png … product_3.png
│   ├── product_1.jpg … product_3.jpg
│   ├── quite-time.{png,mp4}       # Hero / gallery moments
│   ├── cozy-naps.{png,mp4}
│   ├── happy-walks.{png,mp4}
│   ├── balanced-days.{png,mp4}
│   ├── easy-evenings.{png,mp4}
│   ├── gentle-comfort.{png,mp4}
│   ├── endocannabinoid-system.{png,mp4}
│   ├── bkgd-video.mp4
│   ├── small-dog.png, medium-dog.png, large-dog.png
│   └── favicon.{svg,png}, favicon-source.png
├── .env.example            # Optional payment, email, export, admin password
├── README.md
└── README.txt              # Original quick-start notes
```

## Getting Started

No install or frontend build step required. **Checkout and order placement need PHP** and Apache with `mod_rewrite` (or equivalent URL rewriting).

### Laragon (recommended for full stack)

With [Laragon](https://laragon.org/), place the repo under `www/calmcanine` and visit:

```
http://localhost/calmcanine/
```

Clean URLs (`/product`, `/cart`, `/checkout`, `/account`, `/api/quote`, etc.) are handled by the root `.htaccess`.

### Static preview only

For landing-page work without PHP:

```powershell
# Option 1: open directly
start index.html

# Option 2: serve locally with Python
python -m http.server 8080
# then visit http://localhost:8080
```

Cart and checkout pages will load, but quote and place-order calls will fail without the PHP API.

## Environment Variables

Copy `.env.example` to `.env` and set values when connecting live services:

| Variable | Purpose |
|----------|---------|
| `PAYMENT_PROVIDER` | Payment integration (e.g. `stripe`) |
| `STRIPE_PUBLISHABLE_KEY` | Stripe publishable key for the active environment |
| `STRIPE_SECRET_KEY` | Stripe secret key (`sk_test_` in development) |
| `STRIPE_WEBHOOK_SECRET` | Signing secret for `POST /api/stripe/webhook` (`whsec_`) |
| `APP_URL` | Public site base URL used for Stripe success and cancellation redirects |
| `EMAIL_PROVIDER` | Mail transport (`brevo`) |
| `SMTP_HOST` | SMTP host (default `smtp-relay.brevo.com`) |
| `SMTP_PORT` | SMTP port (default `587`) |
| `SMTP_ENCRYPTION` | `tls` (STARTTLS on 587) or `ssl` (465) |
| `SMTP_USERNAME` | Brevo SMTP login |
| `SMTP_PASSWORD` | Brevo SMTP key |
| `MAIL_FROM` | Verified Brevo sender address |
| `MAIL_FROM_NAME` | From display name |
| `MAIL_OPS_TO` | Inbox for new-order alerts |
| `FULFILLMENT_EXPORT_KEY` | Protects `/api/orders/export` (Bearer token or `?key=`) |
| `ADMIN_PASSWORD` | Password for `/admin` session login (required for admin access) |

Payment uses Stripe Checkout when sandbox credentials are configured. Full card details are entered only on Stripe-hosted pages and never pass through the Calm Canine server. Product photos on Checkout are uploaded from local catalog files (`assets/calm-canine-pouch-v2.png`) to Stripe’s Files API, so Stripe can display them without a public DNS hostname. Set `APP_URL` to the public site origin (Laragon: `http://calmcanine.test`). After a successful sandbox payment, add a webhook for `checkout.session.completed` and `invoice.paid` pointing at `/api/stripe/webhook` and store the signing secret in `STRIPE_WEBHOOK_SECRET`. Order confirmations, ops alerts, contact notifications, and welcome emails send through Brevo SMTP. Contact submissions are archived before notification delivery, and failed SMTP attempts remain visible in admin.

### Contact records and Brevo email setup

1. Verify the sender in Brevo, create an SMTP key, and set `SMTP_USERNAME`, `SMTP_PASSWORD`, `MAIL_FROM`, and `MAIL_OPS_TO`.
2. Submit the contact form. The submission is saved before notification delivery, then the platform emails operations and sends the visitor an acknowledgment.
3. Place an order to test transactional delivery. Saved submissions and outbound attempts appear under **Admin → Emails**.

### Admin setup

1. Copy `.env.example` to `.env` and set `ADMIN_PASSWORD` to a strong password.
2. Visit `/admin` (or `/admin/login`) and sign in.
3. Use the orders list and order detail pages to update fulfillment and email queue status.

Admin is not linked from the public site header — bookmark `/admin` for ops use.

## Deployment

There is **no production hosting configured yet**. The site runs locally via Laragon (see Getting Started).

When ready to go live, deploy the full repo to an Apache/PHP host with `mod_rewrite` enabled. Ensure:

- PHP 8+ with write access to `data/` (orders, users, email queue, fulfillment)
- `.htaccess` honored at the document root
- Optional `.env` for admin password, Brevo SMTP, export key, and payment providers
- No frontend build step — serve the repo root as the document root

## API Reference

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/quote` | Body: `{ items, state }` → normalized lines, subtotal, shipping, tax, total |
| `POST` | `/api/checkout/session` | Validate cart and customer details, then return a Stripe Checkout URL |
| `POST` | `/api/checkout/complete` | Verify a paid Stripe Checkout Session and finalize the order |
| `POST` | `/api/stripe/webhook` | `checkout.session.completed` and `invoice.paid` (signature required) |
| `POST` | `/api/orders/create` | Legacy stub route; disabled whenever `PAYMENT_PROVIDER=stripe` |
| `GET` | `/api/orders/{id}` | Public order payload for confirmation page |
| `POST` | `/api/account/register` | Body: `{ email, password, name?, phone?, shipping? }` → session cookie |
| `POST` | `/api/account/login` | Body: `{ email, password }` → session cookie |
| `POST` | `/api/account/logout` | Clear customer session |
| `GET` | `/api/account/session` | `{ authenticated, user }` |
| `PATCH` | `/api/account/update` | Body: `{ name, phone, shipping }` (auth required) |
| `GET` | `/api/newsletter/status` | `?email=` → `{ subscribed }` |
| `POST` | `/api/newsletter/subscribe` | Body: `{ email, name?, source? }` |
| `GET` | `/api/orders/export` | Fulfillment queue; `?format=csv` for CSV download |
| `POST` | `/api/admin/login` | Body: `{ password }` → session cookie |
| `POST` | `/api/admin/logout` | Clear admin session |
| `GET` | `/api/admin/session` | `{ authenticated }` |
| `GET` | `/api/admin/orders` | Admin order summaries (auth required) |
| `GET` | `/api/admin/orders/{id}` | Full admin order + email queue jobs |
| `PATCH` | `/api/admin/orders/{id}/fulfillment` | Body: `{ status }` (`pending` \| `processing` \| `shipped` \| `cancelled`) |
| `POST` | `/api/admin/orders/{id}/emails/{kind}/mark-sent` | `kind`: `customer` \| `ops` |
| `POST` | `/api/admin/orders/{id}/emails/{kind}/requeue` | Send confirmation or ops email again via Brevo SMTP |

Catalog pricing, shipping rules, and restricted states live in `api/config/catalog.json`. Tax rates are in `api/config/tax-rates.json`.

## Responsive Breakpoints

| Breakpoint | Behavior |
|------------|----------|
| **Desktop** (>1000px) | Orbit hero, hover interactions, product tilt, cursor glow |
| **Tablet** (651px–1000px) | Simplified layouts, adjusted section spacing |
| **Mobile** (≤650px) | Vertical hero story, swipe carousel, single-column grids |
| **Small mobile** (≤380px) | Tighter nav, single-column ingredient list |

## Browser Support

Works in modern browsers that support CSS custom properties, `IntersectionObserver`, `matchMedia`, and ES6+ JavaScript. Video elements use `playsinline` for iOS compatibility.

## Compliance Notes

Before publishing or embedding in production:

1. Review all **CBD-related claims**, dosage wording, age restrictions, and veterinary disclaimers for the market where the product will be sold.
2. Confirm shop, cart, checkout, and API URLs resolve correctly in your deployment environment.
3. Complete a Stripe sandbox checkout, forward `checkout.session.completed` and `invoice.paid` to `/api/stripe/webhook` with `STRIPE_WEBHOOK_SECRET`, and replace test credentials only after the production review.
4. Confirm contact submissions are saved and Brevo SMTP sends notifications, acknowledgments, and transactional mail.
5. Compress large JPG/PNG assets if load time becomes a concern.
6. Footer and serving-section fine print should be reviewed by legal/compliance as needed.

---

© 2026 Calm Canine

# Calm Canine

A marketing site and on-site shop for **Calm Canine** — organic pumpkin and peanut butter wellness treats with CBD isolate, crafted to support calm moments, gentle relief, and everyday balance for dogs of all ages.

**Repository:** [github.com/jmaeacido/calmcanine](https://github.com/jmaeacido/calmcanine)  
**Status:** Local development only — no production hosting yet  
**Shop:** [product](product) — full on-site cart and checkout at `/cart` and `/checkout`

## Overview

Calm Canine combines a static marketing landing page with a lightweight PHP order API. The homepage moves from an emotional hero into product education, science-backed context, serving guidance, lifestyle gallery, and a conversion-focused shop CTA — with a fully polished mobile experience and no frontend build step. Cart, checkout, and order confirmation run in the browser and talk to JSON endpoints backed by file storage.

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
- Live tax and totals from the quote API when a shipping state is selected
- Orders submitted to the PHP API; confirmation page loads order details by ID

### Order API
- **`POST /api/quote`** — normalize line items, compute subtotal, shipping, tax, and total
- **`POST /api/orders/create`** — validate payload, process payment (stub), persist order, queue email and fulfillment
- **`GET /api/orders/{id}`** — return a sanitized order for the confirmation page
- **`GET /api/orders/export`** — fulfillment queue export (JSON or CSV; optional bearer/key auth via `FULFILLMENT_EXPORT_KEY`)
- Orders saved under `data/orders/`; email jobs under `data/email-queue/`; fulfillment queue in `data/fulfillment/queue.jsonl`

### Global
- Sticky header with mobile nav drawer (Escape / outside-click close)
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
| **PHP 8+** | Lightweight JSON API for quotes and orders (~250 lines in `bootstrap.php` + endpoint scripts) |
| **Apache** | Clean URLs and API routing via `.htaccess` (`mod_rewrite`) |
| **Google Fonts** | [Cormorant Garamond](https://fonts.google.com/specimen/Cormorant+Garamond) (brand display), [Montserrat](https://fonts.google.com/specimen/Montserrat) (Gotham stand-in), [Poppins](https://fonts.google.com/specimen/Poppins) (TT Norms stand-in) |
| **Brand palette** | Gold `#c6a262`, forest `#2a3f34`, paper `#eee7e7`, sage `#63735c`, sand `#e6c68e` |
| **Media** | Optimized PNG posters + MP4 loops for hero and gallery moments |

## Project Structure

```
calmcanine/
├── index.html              # Main landing page
├── .htaccess               # Clean URLs for pages and API routes
├── api-client.js           # fetch wrapper for quote / order endpoints
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
│   ├── bootstrap.php       # Catalog, tax, quote, order, queue helpers
│   ├── quote.php           # POST /api/quote
│   ├── orders-create.php   # POST /api/orders/create
│   ├── orders-get.php      # GET /api/orders/{id}
│   ├── orders-export.php   # GET /api/orders/export
│   └── config/
│       ├── catalog.json    # Product, shipping, restricted states
│       └── tax-rates.json  # State tax rates
├── data/                   # Runtime storage (gitignored except .htaccess)
│   ├── orders/             # One JSON file per order
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
├── .env.example            # Optional payment, email, export keys
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

Clean URLs (`/product`, `/cart`, `/checkout`, `/api/quote`, etc.) are handled by the root `.htaccess`.

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
| `STRIPE_SECRET_KEY` | Stripe secret key |
| `EMAIL_PROVIDER` | Email integration (e.g. `resend`) |
| `RESEND_API_KEY` | Resend API key |
| `FULFILLMENT_EXPORT_KEY` | Protects `/api/orders/export` (Bearer token or `?key=`) |

Payment is currently a **stub** (`authorized_stub`); email files are queued but not sent until a provider is wired up.

## Deployment

There is **no production hosting configured yet**. The site runs locally via Laragon (see Getting Started).

When ready to go live, deploy the full repo to an Apache/PHP host with `mod_rewrite` enabled. Ensure:

- PHP 8+ with write access to `data/` (orders, email queue, fulfillment)
- `.htaccess` honored at the document root
- Optional `.env` for export key and future payment/email providers
- No frontend build step — serve the repo root as the document root

## API Reference

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/quote` | Body: `{ items, state }` → normalized lines, subtotal, shipping, tax, total |
| `POST` | `/api/orders/create` | Body: customer, shipping, items, paymentMethod, acceptTerms → order |
| `GET` | `/api/orders/{id}` | Public order payload for confirmation page |
| `GET` | `/api/orders/export` | Fulfillment queue; `?format=csv` for CSV download |

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
3. Replace the **payment stub** with a PCI-compliant provider before accepting real card data.
4. Wire up email delivery for order confirmations and ops notifications.
5. Compress large JPG/PNG assets if load time becomes a concern.
6. Footer and serving-section fine print should be reviewed by legal/compliance as needed.

---

© 2026 Calm Canine

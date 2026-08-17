# Calm Canine

A single-page marketing site for **Calm Canine** — organic pumpkin and peanut butter wellness treats with CBD isolate, crafted to support calm moments, gentle relief, and everyday balance for dogs of all ages.

**Live site:** [calm-canine.vercel.app](https://calm-canine.vercel.app)  
**Repository:** [github.com/jmaeacido/calm-canine](https://github.com/jmaeacido/calm-canine)  
**Shop:** [Native Ceuticals — Calm Canine](https://nativeceuticals.com/product/calm-canine/store/60/)

## Overview

Calm Canine is a static product landing page built for premium pet-wellness storytelling. The page moves from an emotional hero into product education, science-backed context, serving guidance, lifestyle gallery, and a conversion-focused shop CTA — with a fully polished mobile experience and no build step.

## Features

### Hero
- Interactive **orbit showcase** — six lifestyle video cards (Quiet Time, Cozy Naps, Happy Walks, Balanced Days, Easy Evenings, Gentle Relief) arranged around the product pouch
- Hover-driven **calm journey** on desktop — videos play when the product stage is hovered or focused
- **Mobile carousel** — horizontal swipe with scroll-snap, dot indicators, and edge-fade affordance
- Product tilt, cursor glow, trust badges, and scroll cue

### About
- Split-layout product showcase with ingredient grid
- Photoshopped product imagery and benefit copy
- CTAs linked to the live Native Ceuticals product page
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

### Global
- Sticky header with mobile nav drawer (Escape / outside-click close)
- Scroll reveal animations via `IntersectionObserver`
- Safe-area inset support for notched devices
- `prefers-reduced-motion` respected across animations and video playback

## Tech Stack

| Layer | Details |
|-------|---------|
| **HTML5** | Semantic, accessible markup |
| **CSS3** | Custom properties, grid/flex, clamp-based typography, scroll animations (~2,850 lines) |
| **Vanilla JavaScript** | No framework, no build step (~250 lines) |
| **Google Fonts** | [Cormorant Garamond](https://fonts.google.com/specimen/Cormorant+Garamond) (brand display), [Montserrat](https://fonts.google.com/specimen/Montserrat) (Gotham stand-in), [Poppins](https://fonts.google.com/specimen/Poppins) (TT Norms stand-in) |
| **Brand palette** | Gold `#c6a262`, forest `#2a3f34`, paper `#eee7e7`, sage `#63735c`, sand `#e6c68e` |
| **Media** | Optimized PNG posters + MP4 loops for hero and gallery moments |

## Project Structure

```
calm-canine/
├── index.html              # Main landing page (~330 lines)
├── style.css               # Global styles, sections, responsive breakpoints
├── script.js               # Interactions (orbit, carousel, nav, video, parallax)
├── assets/
│   ├── calm-canine-pouch-v2.png   # Hero / shop product cutout
│   ├── calm-canine-pouch.png      # Original pouch asset
│   ├── product_1.png              # Treat squares
│   ├── product_2.png              # About-section pouch
│   ├── product_3.png              # Gallery featured pouch
│   ├── product_1.jpg … product_3.jpg   # Gallery lifestyle sources
│   ├── quite-time.{png,mp4}       # Hero / gallery moment
│   ├── cozy-naps.{png,mp4}
│   ├── happy-walks.{png,mp4}
│   ├── balanced-days.{png,mp4}
│   ├── easy-evenings.{png,mp4}
│   ├── gentle-comfort.{png,mp4}
│   ├── endocannabinoid-system.{png,mp4}
│   ├── small-dog.png
│   ├── medium-dog.png
│   └── large-dog.png
├── README.md
└── README.txt              # Original quick-start notes
```

## Getting Started

No install or build step required.

```powershell
# Option 1: open directly
start index.html

# Option 2: serve locally with Python
python -m http.server 8080
# then visit http://localhost:8080
```

With [Laragon](https://laragon.org/), the site is typically available at:

```
http://localhost/calm-canine/
```

## Deployment

The site is deployed as a **static site** on [Vercel](https://vercel.com/):

| Setting | Value |
|---------|-------|
| **URL** | https://calm-canine.vercel.app |
| **Framework preset** | Other (static) |
| **Build command** | None |
| **Output directory** | `/` (repo root) |

Connect the GitHub repository and deploy — Vercel serves `index.html` at the root with no build step.

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
2. Confirm shop URLs point to the correct live product page.
3. Compress large JPG/PNG assets if load time becomes a concern.
4. Footer and serving-section fine print should be reviewed by legal/compliance as needed.

---

© 2026 Native Ceuticals

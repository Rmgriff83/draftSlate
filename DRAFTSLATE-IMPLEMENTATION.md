# DraftSlate — Full Implementation Document

> **Version:** 1.0 | **Date:** March 2026
> **Source of Truth:** `draftslate-product-spec.txt` (v1.0)
> **Purpose:** Complete technical reference for building the DraftSlate platform from zero to MVP.

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Design System & Visual Identity](#2-design-system--visual-identity)
3. [Project Structure & Scaffolding](#3-project-structure--scaffolding)
4. [Database Schema & Migrations](#4-database-schema--migrations)
5. [Backend: Models, Relationships & Business Logic](#5-backend-models-relationships--business-logic)
6. [API Design & Route Map](#6-api-design--route-map)
7. [Authentication & Authorization](#7-authentication--authorization)
8. [Background Jobs & Queue Architecture](#8-background-jobs--queue-architecture)
9. [Odds Integration (the-odds-api.com)](#9-odds-integration-the-odds-apicom)
10. [Real-Time: Broadcasting, WebSockets & Events](#10-real-time-broadcasting-websockets--events)
11. [Payment System (Stripe)](#11-payment-system-stripe)
12. [Frontend: Vue 3 Application Architecture](#12-frontend-vue-3-application-architecture)
13. [Frontend: Views, Components & User Flows](#13-frontend-views-components--user-flows)
14. [Notifications](#14-notifications)
15. [Rate Limiting & Throttling](#15-rate-limiting--throttling)
16. [Testing Strategy](#16-testing-strategy)
17. [Deployment & Infrastructure](#17-deployment--infrastructure)
18. [Environment Variables Reference](#18-environment-variables-reference)
19. [Implementation Phases & Build Order](#19-implementation-phases--build-order)

---

## 1. Architecture Overview

### System Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                             │
│  Vue 3 SPA (Vite) ─ Pinia ─ Vue Router ─ Tailwind CSS         │
│  Laravel Echo (WebSocket client) ─ Axios (HTTP)                │
└──────────────────────────┬──────────────────────────────────────┘
                           │ HTTPS / WSS
┌──────────────────────────▼──────────────────────────────────────┐
│                       APPLICATION LAYER                         │
│  Laravel 11 (PHP 8.2+)                                         │
│  ├── Sanctum (SPA auth + API tokens)                           │
│  ├── Eloquent ORM                                              │
│  ├── Laravel Broadcasting (Pusher/Soketi driver)               │
│  ├── Laravel Queue (Redis driver)                              │
│  ├── Laravel Horizon (queue dashboard)                         │
│  ├── Laravel Cashier (Stripe subscriptions)                    │
│  ├── Laravel Socialite (Google OAuth)                          │
│  ├── Laravel Notifications (mail + push)                       │
│  └── Laravel Task Scheduler                                    │
└─────┬──────────────┬──────────────┬─────────────┬──────────────┘
      │              │              │             │
┌─────▼─────┐ ┌──────▼─────┐ ┌─────▼────┐ ┌─────▼──────┐
│  MySQL 8  │ │   Redis    │ │  Soketi/  │ │    S3      │
│  (primary │ │  (queues,  │ │  Pusher   │ │  (assets,  │
│   data)   │ │  cache,    │ │  (WS)     │ │  uploads)  │
│           │ │  sessions) │ │           │ │            │
└───────────┘ └────────────┘ └──────────┘ └────────────┘
                                                │
                              ┌─────────────────┘
                              │
                    ┌─────────▼──────────┐
                    │  External APIs     │
                    │  ├─ the-odds-api   │
                    │  ├─ Stripe         │
                    │  ├─ Mailgun        │
                    │  └─ FCM / APNs     │
                    └────────────────────┘
```

### Tech Stack Summary

| Layer | Technology | Purpose |
|-------|-----------|---------|
| Frontend Framework | Vue 3 (Composition API, `<script setup>`) | SPA |
| Frontend Language | JavaScript (no TypeScript in MVP) | |
| State Management | Pinia | Global reactive state |
| Routing | Vue Router | Client-side routing with auth guards |
| Form Validation | Vuelidate | Client-side field validation |
| Build Tool | Vite | Dev server + production bundling |
| CSS Framework | Tailwind CSS | Utility-first, mobile-first |
| HTTP Client | Axios | API communication |
| Real-Time Client | Laravel Echo + Pusher JS | WebSocket for draft |
| Backend Framework | Laravel 11 | API + business logic |
| Backend Language | PHP 8.2+ | |
| Database | MySQL 8.0+ | Primary data store |
| Cache / Queue | Redis | Queue driver, cache, sessions |
| Queue Dashboard | Laravel Horizon | Job monitoring |
| Auth | Laravel Sanctum | SPA cookie auth + API tokens |
| OAuth | Laravel Socialite | Google login |
| Payments | Stripe + Laravel Cashier | Buy-ins, payouts, subscriptions |
| WebSocket Server | Soketi (self-hosted) or Pusher | Broadcasting |
| Object Storage | S3-compatible | Team logos, avatars |
| Email | Mailgun via Laravel Mail | Transactional email |
| Push Notifications | FCM / APNs | Mobile push |

---

## 2. Design System & Visual Identity

### Design Philosophy

DraftSlate's visual identity sits at the intersection of two design languages:

- **PrizePicks** — Bold, energetic, animated. Vibrant color palette with purples, greens, and glowing accent elements. Fluid micro-animations on state transitions (card reveals, score updates, pick confirmations). The app *feels alive*.
- **ESPN Fantasy** — Clean, structured, information-dense without clutter. Professional typography, muted card surfaces, clear data hierarchy. The app *feels trustworthy*.

**DraftSlate's target:** The energy and color vibrancy of PrizePicks married to the layout discipline and informational clarity of ESPN Fantasy. Animations are purposeful (not decorative) — they reinforce state changes and reward user actions. The color palette is bold but grounded. Typography is clean and legible at all sizes.

### Color Palette

```
/* Core Brand Colors */
--ds-primary:          #6C3FE0;    /* Deep violet — brand anchor, primary CTAs */
--ds-primary-light:    #8B6CE6;    /* Lighter violet — hover states, accents */
--ds-primary-dark:     #4A1FB8;    /* Dark violet — pressed states, depth */

/* Accent / Energy Colors (PrizePicks-inspired vitality) */
--ds-accent-green:     #00D26A;    /* Bright green — HIT badges, positive movement, success */
--ds-accent-red:       #FF3B5C;    /* Vivid coral-red — MISS badges, negative movement, errors */
--ds-accent-gold:      #FFB800;    /* Gold — championships, streak highlights, premium */
--ds-accent-blue:      #2B7FFF;    /* Electric blue — informational, links, secondary actions */

/* Surface Colors (ESPN-inspired structure) */
--ds-bg-primary:       #0D0F14;    /* Near-black — main background (dark mode) */
--ds-bg-secondary:     #161A22;    /* Dark charcoal — card surfaces, panels */
--ds-bg-tertiary:      #1E232E;    /* Lighter charcoal — elevated cards, modals */
--ds-bg-hover:         #252B38;    /* Hover surface state */

/* Light Mode Equivalents */
--ds-bg-primary-light: #F5F6FA;    /* Off-white — main background (light mode) */
--ds-bg-secondary-light: #FFFFFF;  /* White — card surfaces */
--ds-bg-tertiary-light:  #F0F1F5; /* Light gray — elevated cards */

/* Text Colors */
--ds-text-primary:     #FFFFFF;    /* Primary text (dark mode) */
--ds-text-secondary:   #8E95A8;   /* Secondary/muted text */
--ds-text-tertiary:    #5A6178;   /* Disabled/hint text */
--ds-text-primary-light: #1A1D26; /* Primary text (light mode) */

/* Utility */
--ds-border:           #2A2F3D;    /* Card borders, dividers (dark mode) */
--ds-border-light:     #E2E4EA;    /* Card borders (light mode) */
--ds-overlay:          rgba(0, 0, 0, 0.6); /* Modal/sheet overlays */
```

### Typography

```
/* Font Stack */
--ds-font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
--ds-font-mono: 'JetBrains Mono', 'SF Mono', 'Fira Code', monospace;

/* Scale */
--ds-text-xs:    0.75rem;   /* 12px — fine print, timestamps */
--ds-text-sm:    0.875rem;  /* 14px — secondary labels, badges */
--ds-text-base:  1rem;      /* 16px — body text, card content */
--ds-text-lg:    1.125rem;  /* 18px — section headers, emphasis */
--ds-text-xl:    1.25rem;   /* 20px — screen titles */
--ds-text-2xl:   1.5rem;    /* 24px — hero numbers, scores */
--ds-text-3xl:   2rem;      /* 32px — matchup score display */
--ds-text-4xl:   2.5rem;    /* 40px — championship/big number moments */

/* Weights */
--ds-font-normal:    400;
--ds-font-medium:    500;
--ds-font-semibold:  600;
--ds-font-bold:      700;
--ds-font-extrabold: 800;  /* Score displays, hero moments only */
```

### Animation System

Animations follow PrizePicks' philosophy — *every state change has a tactile response* — but with ESPN's restraint on timing and frequency.

#### Core Animation Tokens

```css
/* Duration */
--ds-anim-fast:     150ms;   /* Micro: button press, toggle, hover */
--ds-anim-normal:   250ms;   /* Standard: card transitions, tab switches */
--ds-anim-slow:     400ms;   /* Emphasis: modals, bottom sheets, reveals */
--ds-anim-dramatic: 800ms;   /* Celebration: HIT/MISS flash, win/loss reveal */

/* Easing */
--ds-ease-out:      cubic-bezier(0.16, 1, 0.3, 1);    /* Deceleration — entering elements */
--ds-ease-in-out:   cubic-bezier(0.65, 0, 0.35, 1);   /* Symmetric — sliding/swiping */
--ds-ease-bounce:   cubic-bezier(0.34, 1.56, 0.64, 1); /* Overshoot — celebratory moments */
--ds-ease-spring:   cubic-bezier(0.175, 0.885, 0.32, 1.275); /* Spring — card snaps */
```

#### Key Animation Behaviors

| Trigger | Animation | Duration | Easing |
|---------|-----------|----------|--------|
| Pick card HIT result | Green glow flash → pulse → settle to badge | `dramatic` | `bounce` |
| Pick card MISS result | Red flash → subtle shake → settle to badge | `dramatic` | `ease-out` |
| Matchup win reveal | Score counter animates up, confetti burst, WIN banner slides in | `dramatic` | `bounce` |
| Draft pick selection | Card lifts, glows primary, flies to roster panel | `slow` | `spring` |
| Draft timer < 10s | Timer pulses red with increasing frequency | `fast` repeating | `ease-in-out` |
| Tab switch (swipe) | Content slides left/right with underline follow | `normal` | `ease-in-out` |
| Bottom sheet open | Slides up from bottom, backdrop fades in | `slow` | `ease-out` |
| Card hover/press | Subtle lift (translateY -2px) + shadow deepen | `fast` | `ease-out` |
| Odds movement arrow | Fade-in + subtle vertical slide (green up / red down) | `normal` | `ease-out` |
| Score counter update | Number rolls/ticks to new value (odometer style) | `slow` | `ease-out` |
| Toast notification | Slides down from top, auto-dismiss after 4s | `normal` | `ease-out` |
| Lock countdown < 1hr | Countdown badge pulses amber | `normal` repeating | `ease-in-out` |

#### Reduced Motion

All animations respect `prefers-reduced-motion: reduce`. When active, animations are replaced with instant state changes (opacity crossfades only, no transforms).

### Spacing & Layout

```
/* Spacing Scale (4px base) */
--ds-space-1:  0.25rem;   /* 4px */
--ds-space-2:  0.5rem;    /* 8px */
--ds-space-3:  0.75rem;   /* 12px */
--ds-space-4:  1rem;      /* 16px */
--ds-space-5:  1.25rem;   /* 20px */
--ds-space-6:  1.5rem;    /* 24px */
--ds-space-8:  2rem;      /* 32px */
--ds-space-10: 2.5rem;    /* 40px */
--ds-space-12: 3rem;      /* 48px */

/* Border Radius */
--ds-radius-sm:   6px;    /* Buttons, badges */
--ds-radius-md:   12px;   /* Cards */
--ds-radius-lg:   16px;   /* Modals, bottom sheets */
--ds-radius-xl:   24px;   /* Hero cards */
--ds-radius-full: 9999px; /* Avatars, pills */

/* Shadows (dark mode — subtle glows rather than drops) */
--ds-shadow-sm:   0 1px 3px rgba(0, 0, 0, 0.3);
--ds-shadow-md:   0 4px 12px rgba(0, 0, 0, 0.4);
--ds-shadow-lg:   0 8px 24px rgba(0, 0, 0, 0.5);
--ds-shadow-glow: 0 0 20px rgba(108, 63, 224, 0.3);  /* Primary glow */
--ds-shadow-hit:  0 0 16px rgba(0, 210, 106, 0.4);    /* HIT glow */
--ds-shadow-miss: 0 0 16px rgba(255, 59, 92, 0.4);    /* MISS glow */
```

### Tailwind Configuration

```js
// tailwind.config.js
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        ds: {
          primary:       '#6C3FE0',
          'primary-light': '#8B6CE6',
          'primary-dark':  '#4A1FB8',
          green:         '#00D26A',
          red:           '#FF3B5C',
          gold:          '#FFB800',
          blue:          '#2B7FFF',
          bg: {
            primary:     '#0D0F14',
            secondary:   '#161A22',
            tertiary:    '#1E232E',
            hover:       '#252B38',
          },
          text: {
            primary:     '#FFFFFF',
            secondary:   '#8E95A8',
            tertiary:    '#5A6178',
          },
          border:        '#2A2F3D',
        },
      },
      fontFamily: {
        sans: ['Inter', ...defaultTheme.fontFamily.sans],
        mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
      },
      borderRadius: {
        ds: '12px',
      },
      transitionTimingFunction: {
        'ds-out':    'cubic-bezier(0.16, 1, 0.3, 1)',
        'ds-bounce': 'cubic-bezier(0.34, 1.56, 0.64, 1)',
        'ds-spring': 'cubic-bezier(0.175, 0.885, 0.32, 1.275)',
      },
      animation: {
        'hit-flash':   'hitFlash 800ms cubic-bezier(0.34, 1.56, 0.64, 1)',
        'miss-flash':  'missFlash 800ms cubic-bezier(0.16, 1, 0.3, 1)',
        'pulse-red':   'pulseRed 1s ease-in-out infinite',
        'score-pop':   'scorePop 400ms cubic-bezier(0.34, 1.56, 0.64, 1)',
        'slide-up':    'slideUp 400ms cubic-bezier(0.16, 1, 0.3, 1)',
      },
      keyframes: {
        hitFlash: {
          '0%':   { boxShadow: '0 0 0 rgba(0,210,106,0)', transform: 'scale(1)' },
          '30%':  { boxShadow: '0 0 24px rgba(0,210,106,0.6)', transform: 'scale(1.03)' },
          '100%': { boxShadow: '0 0 8px rgba(0,210,106,0.2)', transform: 'scale(1)' },
        },
        missFlash: {
          '0%':   { boxShadow: '0 0 0 rgba(255,59,92,0)', transform: 'translateX(0)' },
          '20%':  { boxShadow: '0 0 24px rgba(255,59,92,0.6)', transform: 'translateX(-3px)' },
          '40%':  { transform: 'translateX(3px)' },
          '60%':  { transform: 'translateX(-2px)' },
          '100%': { boxShadow: '0 0 8px rgba(255,59,92,0.2)', transform: 'translateX(0)' },
        },
        pulseRed: {
          '0%, 100%': { opacity: '1' },
          '50%':      { opacity: '0.5', color: '#FF3B5C' },
        },
        scorePop: {
          '0%':   { transform: 'scale(1)' },
          '50%':  { transform: 'scale(1.2)' },
          '100%': { transform: 'scale(1)' },
        },
        slideUp: {
          '0%':   { transform: 'translateY(100%)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
      },
    },
  },
  plugins: [],
}
```

### Component Design Patterns

#### Card Anatomy (Slate Pick Card)

```
┌──────────────────────────────────────────────────────────┐
│  [Slot Label]                      [Lock Countdown/Badge]│
│                                                          │
│  Pick Description Text (truncated to 2 lines)            │
│  Player Name · Team vs Opponent                          │
│                                                          │
│  ┌──────────┐  ┌──────────────────────────┐              │
│  │ -160 odds│  │ ▲ moved from -180        │  [HIT/MISS] │
│  └──────────┘  └──────────────────────────┘              │
└──────────────────────────────────────────────────────────┘
```

- Card background: `ds-bg-secondary` with `ds-border` 1px border
- HIT state: left border becomes 3px `ds-green`, glow shadow
- MISS state: left border becomes 3px `ds-red`, muted opacity
- LOCKED state: subtle lock icon overlay, card slightly muted
- PENDING: standard state, no special treatment

#### Bottom Sheet Pattern

Used for pick details, swap confirmation, draft pick confirmation on mobile.

- Overlay: `ds-overlay` backdrop
- Sheet: `ds-bg-tertiary`, `ds-radius-lg` top corners
- Drag handle: 40px wide, 4px tall, `ds-text-tertiary` centered bar
- Slides up with `slide-up` animation
- Snap points: 50% and 90% of viewport height

---

## 3. Project Structure & Scaffolding

### Monorepo Structure

```
draftSlate/
├── backend/                          # Laravel application
│   ├── app/
│   │   ├── Console/
│   │   │   └── Kernel.php            # Scheduler definitions
│   │   ├── Events/
│   │   │   ├── DraftPickMade.php
│   │   │   ├── DraftAdvanced.php
│   │   │   ├── DraftStarted.php
│   │   │   ├── DraftCompleted.php
│   │   │   ├── PickLocked.php
│   │   │   ├── PickGraded.php
│   │   │   ├── MatchupResolved.php
│   │   │   └── SlatePoolReady.php
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Auth/
│   │   │   │   │   ├── LoginController.php
│   │   │   │   │   ├── RegisterController.php
│   │   │   │   │   ├── SocialAuthController.php
│   │   │   │   │   └── PasswordResetController.php
│   │   │   │   ├── LeagueController.php
│   │   │   │   ├── LeagueMembershipController.php
│   │   │   │   ├── DraftController.php
│   │   │   │   ├── SlateController.php
│   │   │   │   ├── MatchupController.php
│   │   │   │   ├── StandingsController.php
│   │   │   │   ├── ProfileController.php
│   │   │   │   ├── FeedController.php
│   │   │   │   ├── PaymentController.php
│   │   │   │   ├── SubscriptionController.php
│   │   │   │   ├── NotificationController.php
│   │   │   │   └── Admin/
│   │   │   │       ├── AdminLeagueController.php
│   │   │   │       ├── AdminPayoutController.php
│   │   │   │       └── AdminSettingsController.php
│   │   │   ├── Middleware/
│   │   │   │   ├── EnsureLeagueMember.php
│   │   │   │   ├── EnsureCommissioner.php
│   │   │   │   ├── EnsureAdmin.php
│   │   │   │   └── ThrottleOddsRefresh.php
│   │   │   ├── Requests/
│   │   │   │   ├── CreateLeagueRequest.php
│   │   │   │   ├── JoinLeagueRequest.php
│   │   │   │   ├── DraftPickRequest.php
│   │   │   │   ├── SlateSwapRequest.php
│   │   │   │   └── UpdateProfileRequest.php
│   │   │   └── Resources/
│   │   │       ├── LeagueResource.php
│   │   │       ├── LeagueMemberResource.php
│   │   │       ├── PickSelectionResource.php
│   │   │       ├── SlatePickResource.php
│   │   │       ├── MatchupResource.php
│   │   │       ├── StandingsResource.php
│   │   │       ├── FeedItemResource.php
│   │   │       └── UserProfileResource.php
│   │   ├── Jobs/
│   │   │   ├── SlatePoolBuildJob.php
│   │   │   ├── OddsRefreshJob.php
│   │   │   ├── DraftAutoPickJob.php
│   │   │   ├── DraftAdvanceJob.php
│   │   │   ├── SlateLockJob.php
│   │   │   ├── ResultGradingJob.php
│   │   │   ├── MatchupScoreJob.php
│   │   │   ├── StandingsUpdateJob.php
│   │   │   ├── PayoutJob.php
│   │   │   ├── RefundJob.php
│   │   │   └── NotificationDispatchJob.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── League.php
│   │   │   ├── LeagueMembership.php
│   │   │   ├── Season.php
│   │   │   ├── WeeklyMatchup.php
│   │   │   ├── SlatePool.php
│   │   │   ├── PickSelection.php
│   │   │   ├── SlatePick.php
│   │   │   ├── DraftState.php
│   │   │   ├── Transaction.php
│   │   │   ├── Notification.php
│   │   │   ├── PlatformSetting.php
│   │   │   ├── FeedItem.php
│   │   │   └── JobLog.php
│   │   ├── Notifications/
│   │   │   ├── DraftReminder.php
│   │   │   ├── YourTurnToPick.php
│   │   │   ├── AutoPickMade.php
│   │   │   ├── PickLocked.php
│   │   │   ├── WeeklyResults.php
│   │   │   ├── PayoutProcessed.php
│   │   │   └── LeagueInvite.php
│   │   ├── Policies/
│   │   │   ├── LeaguePolicy.php
│   │   │   ├── SlatePickPolicy.php
│   │   │   └── MatchupPolicy.php
│   │   └── Services/
│   │       ├── OddsApiService.php
│   │       ├── SlatePoolService.php
│   │       ├── DraftService.php
│   │       ├── SlateManagementService.php
│   │       ├── ScoringService.php
│   │       ├── StandingsService.php
│   │       ├── PayoutService.php
│   │       ├── DraftOrderService.php
│   │       ├── MatchupSchedulerService.php
│   │       └── OddsMathService.php
│   ├── config/
│   │   ├── draftslate.php             # App-specific config
│   │   └── horizon.php                # Queue worker config
│   ├── database/
│   │   ├── migrations/                # All migration files
│   │   ├── seeders/
│   │   │   ├── DatabaseSeeder.php
│   │   │   ├── PlatformSettingsSeeder.php
│   │   │   └── TestLeagueSeeder.php
│   │   └── factories/                 # Model factories
│   ├── routes/
│   │   ├── api.php                    # All API routes
│   │   ├── channels.php               # Broadcast channel auth
│   │   └── console.php                # Artisan commands
│   ├── tests/
│   │   ├── Feature/
│   │   └── Unit/
│   ├── .env.example
│   ├── composer.json
│   └── artisan
│
├── frontend/                          # Vue 3 SPA
│   ├── public/
│   │   └── favicon.svg
│   ├── src/
│   │   ├── assets/
│   │   │   ├── css/
│   │   │   │   ├── main.css           # Tailwind imports + custom tokens
│   │   │   │   └── animations.css     # Custom keyframes & animation classes
│   │   │   ├── icons/                 # SVG icon components
│   │   │   └── images/               # Static images, logos
│   │   ├── components/
│   │   │   ├── common/
│   │   │   │   ├── AppBottomNav.vue
│   │   │   │   ├── AppTopBar.vue
│   │   │   │   ├── BottomSheet.vue
│   │   │   │   ├── Badge.vue
│   │   │   │   ├── Button.vue
│   │   │   │   ├── Card.vue
│   │   │   │   ├── CountdownTimer.vue
│   │   │   │   ├── EmptyState.vue
│   │   │   │   ├── LoadingSpinner.vue
│   │   │   │   ├── Modal.vue
│   │   │   │   ├── OddsBadge.vue
│   │   │   │   ├── OddsDelta.vue
│   │   │   │   ├── OutcomeBadge.vue
│   │   │   │   ├── ScoreCounter.vue
│   │   │   │   ├── StatusBadge.vue
│   │   │   │   ├── TabBar.vue
│   │   │   │   ├── Toast.vue
│   │   │   │   └── Avatar.vue
│   │   │   ├── dashboard/
│   │   │   │   ├── LeagueCardRow.vue
│   │   │   │   ├── LeagueCard.vue
│   │   │   │   ├── AlertBanner.vue
│   │   │   │   ├── QuickStats.vue
│   │   │   │   └── FeedPreview.vue
│   │   │   ├── draft/
│   │   │   │   ├── DraftLobby.vue
│   │   │   │   ├── DraftBoard.vue
│   │   │   │   ├── DraftTimer.vue
│   │   │   │   ├── AvailablePicksList.vue
│   │   │   │   ├── PickCard.vue
│   │   │   │   ├── DraftRosterPanel.vue
│   │   │   │   ├── DraftOrderDisplay.vue
│   │   │   │   ├── DraftPickConfirm.vue
│   │   │   │   └── AutoPickNotice.vue
│   │   │   ├── league/
│   │   │   │   ├── LeagueHeader.vue
│   │   │   │   ├── LeagueSubNav.vue
│   │   │   │   ├── MyPicksTab.vue
│   │   │   │   ├── MatchupTab.vue
│   │   │   │   ├── StandingsTab.vue
│   │   │   │   ├── SlatePickCard.vue
│   │   │   │   ├── PickDetailSheet.vue
│   │   │   │   ├── MatchupScoreHeader.vue
│   │   │   │   ├── MatchupPickRow.vue
│   │   │   │   ├── StandingsRow.vue
│   │   │   │   ├── TeamDetailPopup.vue
│   │   │   │   ├── PlayoffBracket.vue
│   │   │   │   └── WeekSelector.vue
│   │   │   ├── leagues/
│   │   │   │   ├── LeagueListCard.vue
│   │   │   │   ├── LeagueBrowser.vue
│   │   │   │   └── LeagueCreationWizard.vue
│   │   │   ├── feed/
│   │   │   │   ├── FeedItem.vue
│   │   │   │   └── FeedFilter.vue
│   │   │   ├── profile/
│   │   │   │   ├── CareerStats.vue
│   │   │   │   ├── BadgeGrid.vue
│   │   │   │   ├── OddsDriftChart.vue
│   │   │   │   └── LeagueHistory.vue
│   │   │   └── auth/
│   │   │       ├── LoginForm.vue
│   │   │       ├── RegisterForm.vue
│   │   │       └── SocialLoginButton.vue
│   │   ├── composables/
│   │   │   ├── useAuth.js
│   │   │   ├── useDraft.js
│   │   │   ├── useLeague.js
│   │   │   ├── useSlate.js
│   │   │   ├── useMatchup.js
│   │   │   ├── useCountdown.js
│   │   │   ├── useWebSocket.js
│   │   │   ├── useToast.js
│   │   │   ├── useAnimation.js
│   │   │   └── useOdds.js
│   │   ├── layouts/
│   │   │   ├── AppLayout.vue          # Authenticated shell (top bar + bottom nav)
│   │   │   └── AuthLayout.vue         # Unauthenticated (login/register)
│   │   ├── pages/
│   │   │   ├── MarketingHome.vue
│   │   │   ├── LoginPage.vue
│   │   │   ├── RegisterPage.vue
│   │   │   ├── DashboardPage.vue
│   │   │   ├── LeaguesPage.vue
│   │   │   ├── LeagueViewPage.vue
│   │   │   ├── DraftPage.vue
│   │   │   ├── FeedPage.vue
│   │   │   ├── ProfilePage.vue
│   │   │   ├── SettingsPage.vue
│   │   │   ├── LeagueCreatePage.vue
│   │   │   ├── LeagueBrowsePage.vue
│   │   │   └── NotFoundPage.vue
│   │   ├── router/
│   │   │   └── index.js               # Vue Router config with guards
│   │   ├── stores/
│   │   │   ├── auth.js
│   │   │   ├── leagues.js
│   │   │   ├── draft.js
│   │   │   ├── slate.js
│   │   │   ├── matchup.js
│   │   │   ├── feed.js
│   │   │   ├── profile.js
│   │   │   └── notifications.js
│   │   ├── utils/
│   │   │   ├── api.js                 # Axios instance + interceptors
│   │   │   ├── echo.js                # Laravel Echo setup
│   │   │   ├── odds.js                # Odds formatting/math helpers
│   │   │   ├── date.js                # Date formatting helpers
│   │   │   └── validators.js          # Custom Vuelidate validators
│   │   ├── App.vue
│   │   └── main.js
│   ├── index.html
│   ├── vite.config.js
│   ├── tailwind.config.js
│   ├── postcss.config.js
│   └── package.json
│
├── DRAFTSLATE-IMPLEMENTATION.md       # This document
├── draftslate-product-spec.txt        # Source product spec
└── .gitignore
```

### Initial Scaffolding Commands

```bash
# Backend
composer create-project laravel/laravel backend
cd backend
composer require laravel/sanctum laravel/socialite laravel/cashier laravel/horizon
php artisan install:broadcasting  # sets up Pusher/Soketi broadcasting

# Frontend
npm create vite@latest frontend -- --template vue
cd frontend
npm install vue-router@4 pinia @vuelidate/core @vuelidate/validators
npm install axios laravel-echo pusher-js
npm install -D tailwindcss @tailwindcss/forms postcss autoprefixer
npx tailwindcss init -p
```

---

## 4. Database Schema & Migrations

### Entity Relationship Diagram

```
users ─────┬──< league_memberships >──── leagues
           │                               │
           │                               ├──< seasons
           │                               │
           │                               ├──< slate_pools ──< pick_selections
           │                               │
           │                               └──< weekly_matchups
           │
           ├──< transactions
           │
           ├──< notifications_log
           │
           └──< subscriptions (Cashier)

league_memberships ──< slate_picks >── pick_selections

league_memberships ──< weekly_matchups (as home_team / away_team)

draft_states ──── leagues (1:1 per active draft)

feed_items ──── leagues (polymorphic or scoped)
```

### Migration Files

#### `create_users_table` (extends Laravel default)

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('display_name', 50);
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password')->nullable();          // nullable for social-only
    $table->string('avatar_url')->nullable();
    $table->string('google_id')->nullable()->unique();
    $table->enum('role', ['user', 'admin'])->default('user');
    $table->integer('max_leagues')->default(10);     // simultaneous league cap
    $table->rememberToken();
    $table->timestamps();
});
```

#### `create_leagues_table`

```php
Schema::create('leagues', function (Blueprint $table) {
    $table->id();
    $table->foreignId('commissioner_id')->constrained('users');
    $table->string('name', 100);
    $table->enum('type', ['public', 'private'])->default('public');
    $table->enum('state', ['pending', 'active', 'playoffs', 'completed', 'cancelled'])
          ->default('pending');
    $table->integer('max_teams');                     // must be even, 4-14
    $table->decimal('buy_in', 8, 2);                 // min $5.00
    $table->json('payout_structure');                 // e.g. {"1": 100} or {"1": 80, "2": 15, "3": 5}
    $table->integer('starter_slots')->default(5);
    $table->integer('bench_slots')->default(3);
    $table->enum('odds_mode', ['global_floor', 'per_slot_bands'])->default('global_floor');
    $table->integer('global_odds_floor')->default(-250); // American odds
    $table->json('slot_bands')->nullable();           // per-slot bands config
    $table->integer('bench_floor')->nullable();       // bench floor for per_slot_bands mode
    $table->tinyInteger('draft_day')->default(2);     // 0=Sun, 1=Mon, ..., 6=Sat (2=Tue)
    $table->time('draft_time')->default('20:00:00');
    $table->string('draft_timezone', 50)->default('America/New_York');
    $table->integer('pick_timer_seconds')->default(60);
    $table->integer('regular_season_weeks')->default(14);
    $table->enum('playoff_format', ['A', 'B', 'C', 'D'])->default('B');
    $table->string('invite_code', 12)->nullable()->unique();
    $table->integer('current_week')->default(0);      // 0 = pre-season
    $table->string('sport', 20)->default('nfl');      // future-proofing
    $table->timestamps();

    $table->index('state');
    $table->index('type');
    $table->index('sport');
});
```

#### `create_league_memberships_table`

```php
Schema::create('league_memberships', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('league_id')->constrained();
    $table->string('team_name', 50);
    $table->string('team_logo_url')->nullable();
    $table->integer('wins')->default(0);
    $table->integer('losses')->default(0);
    $table->integer('ties')->default(0);
    $table->integer('total_correct_picks')->default(0);  // season aggregate
    $table->integer('total_opponent_correct_picks')->default(0);
    $table->integer('playoff_seed')->nullable();
    $table->enum('playoff_bracket', ['winners', 'losers', null])->nullable();
    $table->integer('final_position')->nullable();
    $table->boolean('is_active')->default(true);      // false = ghost team
    $table->timestamps();

    $table->unique(['user_id', 'league_id']);
    $table->index('league_id');
});
```

#### `create_seasons_table`

```php
Schema::create('seasons', function (Blueprint $table) {
    $table->id();
    $table->foreignId('league_id')->constrained();
    $table->integer('year');
    $table->integer('start_week');                    // NFL week number
    $table->integer('end_week');
    $table->integer('playoff_start_week')->nullable();
    $table->enum('status', ['active', 'playoffs', 'completed'])->default('active');
    $table->timestamps();

    $table->unique(['league_id', 'year']);
});
```

#### `create_weekly_matchups_table`

```php
Schema::create('weekly_matchups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('league_id')->constrained();
    $table->integer('week');
    $table->foreignId('home_team_id')->constrained('league_memberships');
    $table->foreignId('away_team_id')->constrained('league_memberships');
    $table->integer('home_score')->nullable();
    $table->integer('away_score')->nullable();
    $table->foreignId('winner_id')->nullable()->constrained('league_memberships');
    $table->boolean('is_tie')->default(false);
    $table->boolean('is_playoff')->default(false);
    $table->string('playoff_round', 30)->nullable();  // 'wild_card', 'semifinal', 'championship', 'consolation', '3rd_place', '5th_place'
    $table->enum('status', ['scheduled', 'in_progress', 'completed'])->default('scheduled');
    $table->timestamps();

    $table->index(['league_id', 'week']);
    $table->unique(['league_id', 'week', 'home_team_id']);
    $table->unique(['league_id', 'week', 'away_team_id']);
});
```

#### `create_slate_pools_table`

```php
Schema::create('slate_pools', function (Blueprint $table) {
    $table->id();
    $table->foreignId('league_id')->constrained();
    $table->integer('week');
    $table->timestamp('snapshot_at');                  // when odds were frozen
    $table->enum('status', ['building', 'ready', 'draft_active', 'draft_complete'])
          ->default('building');
    $table->json('api_metadata')->nullable();          // debug: API call details
    $table->timestamps();

    $table->unique(['league_id', 'week']);
});
```

#### `create_pick_selections_table`

```php
Schema::create('pick_selections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('slate_pool_id')->constrained();
    $table->string('external_id', 100);               // the-odds-api event/market ID
    $table->text('description');                       // plain-English pick description
    $table->string('pick_type', 30);                  // 'player_prop', 'moneyline', 'spread', 'total'
    $table->string('category', 50)->nullable();        // 'receiving_yards', 'receptions', 'passing', etc.
    $table->string('player_name', 100)->nullable();
    $table->string('home_team', 50);
    $table->string('away_team', 50);
    $table->string('game_display', 100);              // "Chiefs vs Raiders"
    $table->timestamp('game_time');                    // kickoff = lock time
    $table->string('sport', 20)->default('nfl');
    $table->integer('snapshot_odds');                  // American odds at pool creation
    $table->integer('current_odds')->nullable();       // last refreshed odds
    $table->timestamp('odds_updated_at')->nullable();
    $table->enum('outcome', ['pending', 'hit', 'miss', 'void'])->default('pending');
    $table->boolean('is_drafted')->default(false);
    $table->timestamps();

    $table->index('slate_pool_id');
    $table->index('game_time');
    $table->index('outcome');
    $table->index(['slate_pool_id', 'is_drafted']);
});
```

#### `create_slate_picks_table`

```php
Schema::create('slate_picks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('league_membership_id')->constrained();
    $table->foreignId('pick_selection_id')->constrained();
    $table->foreignId('slate_pool_id')->constrained();
    $table->integer('week');
    $table->enum('position', ['starter', 'bench'])->default('starter');
    $table->integer('slot_number');                    // 1-5 for starters, 1-3 for bench
    $table->integer('drafted_odds');                   // odds when user picked it
    $table->integer('locked_odds')->nullable();        // odds at game kickoff
    $table->integer('odds_drift')->nullable();         // locked_odds - drafted_odds (implied prob delta)
    $table->boolean('is_locked')->default(false);
    $table->timestamp('locked_at')->nullable();
    $table->integer('draft_round');
    $table->integer('draft_pick_number');              // overall pick # in draft
    $table->timestamps();

    $table->unique(['league_membership_id', 'pick_selection_id']);
    $table->index(['league_membership_id', 'week']);
    $table->index(['slate_pool_id', 'week']);
});
```

#### `create_draft_states_table`

```php
Schema::create('draft_states', function (Blueprint $table) {
    $table->id();
    $table->foreignId('league_id')->constrained();
    $table->foreignId('slate_pool_id')->constrained();
    $table->integer('week');
    $table->enum('status', ['lobby', 'preparing', 'active', 'completed'])->default('lobby');
    $table->json('draft_order');                       // ordered array of membership IDs
    $table->json('draft_order_weights')->nullable();   // transparency: prior week scores + weights
    $table->integer('current_round')->default(1);
    $table->integer('current_pick_index')->default(0); // index into the snake-ordered pick sequence
    $table->foreignId('current_drafter_id')->nullable()->constrained('league_memberships');
    $table->timestamp('current_pick_started_at')->nullable();
    $table->integer('total_rounds');                   // starter_slots + bench_slots
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->unique(['league_id', 'week']);
});
```

#### `create_transactions_table`

```php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('league_id')->constrained();
    $table->enum('type', ['buy_in', 'payout', 'commission', 'refund']);
    $table->decimal('amount', 10, 2);
    $table->string('stripe_payment_intent_id')->nullable();
    $table->string('stripe_transfer_id')->nullable();
    $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'type']);
    $table->index(['league_id', 'type']);
});
```

#### `create_feed_items_table`

```php
Schema::create('feed_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('league_id')->constrained();
    $table->foreignId('user_id')->nullable()->constrained(); // actor
    $table->string('event_type', 50);                 // 'matchup_win', 'pick_hit', 'draft_start', 'joined', 'streak'
    $table->text('message');                           // rendered display text
    $table->json('metadata')->nullable();              // structured data for rich rendering
    $table->timestamps();

    $table->index(['league_id', 'created_at']);
    $table->index(['user_id', 'created_at']);
});
```

#### `create_notifications_log_table`

```php
Schema::create('notifications_log', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('type', 50);                       // 'draft_reminder', 'your_turn', 'results', etc.
    $table->string('channel', 20);                    // 'push', 'email', 'in_app'
    $table->text('content');
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('read_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'created_at']);
});
```

#### `create_platform_settings_table`

```php
Schema::create('platform_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key', 100)->unique();
    $table->text('value');
    $table->string('description')->nullable();
    $table->timestamps();
});
```

#### `create_job_logs_table`

```php
Schema::create('job_logs', function (Blueprint $table) {
    $table->id();
    $table->string('job_type', 80);
    $table->foreignId('league_id')->nullable()->constrained();
    $table->integer('week')->nullable();
    $table->enum('status', ['started', 'completed', 'failed']);
    $table->json('context')->nullable();               // input params, debug info
    $table->text('error_message')->nullable();
    $table->timestamp('started_at');
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->index(['job_type', 'status']);
    $table->index(['league_id', 'week']);
});
```

---

## 5. Backend: Models, Relationships & Business Logic

### Model Relationships

```php
// User.php
public function leagueMemberships()    { return $this->hasMany(LeagueMembership::class); }
public function commissioning()        { return $this->hasMany(League::class, 'commissioner_id'); }
public function transactions()         { return $this->hasMany(Transaction::class); }
public function leagues()              { return $this->belongsToMany(League::class, 'league_memberships'); }

// League.php
public function commissioner()         { return $this->belongsTo(User::class, 'commissioner_id'); }
public function memberships()          { return $this->hasMany(LeagueMembership::class); }
public function members()              { return $this->belongsToMany(User::class, 'league_memberships'); }
public function seasons()              { return $this->hasMany(Season::class); }
public function slatePools()           { return $this->hasMany(SlatePool::class); }
public function matchups()             { return $this->hasMany(WeeklyMatchup::class); }
public function draftStates()          { return $this->hasMany(DraftState::class); }
public function transactions()         { return $this->hasMany(Transaction::class); }
public function feedItems()            { return $this->hasMany(FeedItem::class); }
public function currentSeason()        { return $this->hasOne(Season::class)->latestOfMany(); }
public function activeDraft()          { return $this->hasOne(DraftState::class)->where('status', '!=', 'completed')->latestOfMany(); }

// LeagueMembership.php
public function user()                 { return $this->belongsTo(User::class); }
public function league()               { return $this->belongsTo(League::class); }
public function slatePicks()           { return $this->hasMany(SlatePick::class); }
public function homeMatchups()         { return $this->hasMany(WeeklyMatchup::class, 'home_team_id'); }
public function awayMatchups()         { return $this->hasMany(WeeklyMatchup::class, 'away_team_id'); }

// SlatePool.php
public function league()               { return $this->belongsTo(League::class); }
public function pickSelections()       { return $this->hasMany(PickSelection::class); }
public function draftState()           { return $this->hasOne(DraftState::class); }

// PickSelection.php
public function slatePool()            { return $this->belongsTo(SlatePool::class); }
public function slatePicks()           { return $this->hasMany(SlatePick::class); }

// SlatePick.php
public function membership()           { return $this->belongsTo(LeagueMembership::class, 'league_membership_id'); }
public function pickSelection()        { return $this->belongsTo(PickSelection::class); }
public function slatePool()            { return $this->belongsTo(SlatePool::class); }

// WeeklyMatchup.php
public function league()               { return $this->belongsTo(League::class); }
public function homeTeam()             { return $this->belongsTo(LeagueMembership::class, 'home_team_id'); }
public function awayTeam()             { return $this->belongsTo(LeagueMembership::class, 'away_team_id'); }
public function winner()               { return $this->belongsTo(LeagueMembership::class, 'winner_id'); }
```

### Key Service Classes

#### `OddsMathService.php` — Odds Conversion & Validation

```php
class OddsMathService
{
    // Convert American odds to implied probability (0.0 to 1.0)
    public function americanToImpliedProbability(int $americanOdds): float;

    // Check if odds meet/exceed a floor (e.g., -250). "Meet" means the pick
    // is at or above the floor — i.e., NOT safer than the floor.
    // -200 meets -250 (riskier). -300 does NOT meet -250 (too safe).
    public function meetsOddsFloor(int $pickOdds, int $floor): bool;

    // Check if odds fall within a band [min, max]
    public function isWithinBand(int $pickOdds, int $bandMin, int $bandMax): bool;

    // Calculate odds drift between two American odds values
    // Returns the delta in implied probability (positive = favorable drift)
    public function calculateOddsDrift(int $draftedOdds, int $lockedOdds): int;
}
```

#### `DraftOrderService.php` — Weighted Random Draft Order

```php
class DraftOrderService
{
    // Generate weighted random draft order for a league's weekly draft.
    // Uses prior week correct pick counts to weight the draw.
    // Week 1: pure random (equal weights).
    public function generateDraftOrder(League $league, int $week): array;

    // Build the full snake-order pick sequence from draft positions.
    // E.g., 10 teams, 8 rounds → 80 picks in snake order.
    public function buildSnakeSequence(array $draftOrder, int $totalRounds): array;
}
```

#### `DraftService.php` — Draft Execution

```php
class DraftService
{
    // Initialize the draft: create DraftState, generate order, broadcast.
    public function initializeDraft(League $league, SlatePool $slatePool): DraftState;

    // Submit a manual pick. Validates eligibility, odds, slot assignment.
    public function submitPick(DraftState $draft, LeagueMembership $drafter, PickSelection $pick, ?int $slotNumber = null): SlatePick;

    // Auto-pick: select best available pick for the current drafter.
    public function autoPickForDrafter(DraftState $draft, LeagueMembership $drafter): SlatePick;

    // Advance the draft to the next pick in the snake sequence.
    public function advanceDraft(DraftState $draft): void;

    // Check if draft is complete (all rounds finished).
    public function isDraftComplete(DraftState $draft): bool;
}
```

#### `SlateManagementService.php` — Lineup Swaps

```php
class SlateManagementService
{
    // Swap a pick between starter and bench positions.
    // Validates: pick is not locked, game hasn't started, positions are valid.
    public function swapPosition(SlatePick $pick, string $targetPosition, int $targetSlot): void;

    // Lock all picks for a given game when kickoff arrives.
    public function lockPicksForGame(string $gameId, Carbon $kickoffTime): void;
}
```

#### `ScoringService.php` — Result Grading & Matchup Scoring

```php
class ScoringService
{
    // Grade a single pick based on final game stats.
    public function gradePick(PickSelection $pick, array $gameResults): void;

    // Score a matchup: count correct starting picks for each team, determine winner.
    public function scoreMatchup(WeeklyMatchup $matchup): void;

    // Grade all picks for a set of completed games.
    public function gradeGameResults(array $gameIds): void;
}
```

#### `MatchupSchedulerService.php` — Weekly Matchup Pairing

```php
class MatchupSchedulerService
{
    // Generate round-robin pairings for regular season, minimizing repeats.
    public function generateSeasonSchedule(League $league): void;

    // Generate playoff bracket matchups based on seeding.
    public function generatePlayoffBracket(League $league, string $format): void;

    // Advance playoff bracket after a round completes.
    public function advancePlayoffRound(League $league): void;
}
```

---

## 6. API Design & Route Map

All API routes are prefixed with `/api/v1` and use JSON request/response format. Authentication via Laravel Sanctum (cookie-based for SPA, token-based for API clients).

### Authentication

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `POST` | `/auth/register` | Create account (display_name, email, password) | No |
| `POST` | `/auth/login` | Email + password login | No |
| `POST` | `/auth/logout` | Destroy session | Yes |
| `GET`  | `/auth/user` | Get authenticated user | Yes |
| `GET`  | `/auth/google/redirect` | Initiate Google OAuth | No |
| `GET`  | `/auth/google/callback` | Google OAuth callback | No |
| `POST` | `/auth/forgot-password` | Send password reset email | No |
| `POST` | `/auth/reset-password` | Reset password with token | No |

### Leagues

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET`    | `/leagues` | Browse public leagues (paginated, filterable) | Yes |
| `POST`   | `/leagues` | Create a league | Yes |
| `GET`    | `/leagues/{league}` | Get league details | Yes (member) |
| `PUT`    | `/leagues/{league}` | Update league settings (commissioner only, pre-season) | Yes (commissioner) |
| `DELETE` | `/leagues/{league}` | Cancel league (commissioner only, pre-season) | Yes (commissioner) |
| `POST`   | `/leagues/{league}/join` | Join a league (pay buy-in) | Yes |
| `POST`   | `/leagues/{league}/leave` | Leave a league (pre-season only) | Yes (member) |
| `GET`    | `/leagues/join/{inviteCode}` | Get private league info by invite code | Yes |

### League Data

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET` | `/leagues/{league}/standings` | Get current standings | Yes (member) |
| `GET` | `/leagues/{league}/standings/{week}` | Get standings snapshot for a specific week | Yes (member) |
| `GET` | `/leagues/{league}/matchups/{week}` | Get all matchups for a week | Yes (member) |
| `GET` | `/leagues/{league}/matchups/{week}/mine` | Get the user's matchup for a week | Yes (member) |
| `GET` | `/leagues/{league}/feed` | Get league activity feed (paginated) | Yes (member) |

### Draft

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET`  | `/leagues/{league}/draft` | Get current draft state | Yes (member) |
| `GET`  | `/leagues/{league}/draft/pool` | Get available picks in the slate pool | Yes (member) |
| `POST` | `/leagues/{league}/draft/pick` | Submit a draft pick | Yes (member, current drafter) |
| `GET`  | `/leagues/{league}/draft/order` | Get draft order + weights | Yes (member) |

### Slate Management

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET`  | `/leagues/{league}/slate/{week}` | Get user's full slate for a week | Yes (member) |
| `POST` | `/leagues/{league}/slate/swap` | Swap a pick between starter/bench | Yes (member) |
| `POST` | `/leagues/{league}/slate/refresh-odds` | Trigger manual odds refresh | Yes (member, rate-limited) |

### Profile & Stats

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET`  | `/profile` | Get authenticated user's full profile + career stats | Yes |
| `PUT`  | `/profile` | Update display name, avatar | Yes |
| `GET`  | `/profile/financial` | Get private financial history | Yes |
| `GET`  | `/users/{user}/profile` | Get public profile of another user | Yes |

### Subscription

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `POST` | `/subscription/create` | Start commissioner subscription | Yes |
| `POST` | `/subscription/cancel` | Cancel subscription | Yes |
| `GET`  | `/subscription/status` | Get subscription status | Yes |

### Notifications

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET`  | `/notifications` | Get user's notification history | Yes |
| `PUT`  | `/notifications/preferences` | Update notification settings | Yes |
| `POST` | `/notifications/register-device` | Register push notification device token | Yes |

### Admin

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET`  | `/admin/leagues` | List all leagues (with filters) | Admin |
| `GET`  | `/admin/payouts/pending` | List pending payouts | Admin |
| `POST` | `/admin/payouts/{league}/process` | Manually trigger payout | Admin |
| `GET`  | `/admin/settings` | Get platform settings | Admin |
| `PUT`  | `/admin/settings` | Update platform settings | Admin |
| `GET`  | `/admin/jobs` | Get recent job log | Admin |

---

## 7. Authentication & Authorization

### Sanctum SPA Authentication

```php
// config/cors.php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'supports_credentials' => true,

// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:5173')),
```

**Flow:**
1. Frontend calls `GET /sanctum/csrf-cookie` to obtain XSRF-TOKEN
2. Frontend sends `POST /api/v1/auth/login` with credentials (Axios sends XSRF cookie automatically)
3. Sanctum creates session, sets encrypted cookie
4. All subsequent API calls include session cookie — Sanctum validates automatically

### Google OAuth (Socialite)

```php
// SocialAuthController.php
public function redirectToGoogle()
{
    return Socialite::driver('google')->redirect();
}

public function handleGoogleCallback()
{
    $googleUser = Socialite::driver('google')->user();

    $user = User::updateOrCreate(
        ['google_id' => $googleUser->getId()],
        [
            'display_name' => $googleUser->getName(),
            'email'        => $googleUser->getEmail(),
            'avatar_url'   => $googleUser->getAvatar(),
        ]
    );

    Auth::login($user);
    return redirect('/dashboard');
}
```

### Authorization Middleware

| Middleware | Purpose |
|-----------|---------|
| `auth:sanctum` | Authenticated user required |
| `EnsureLeagueMember` | User is a member of the route's `{league}` |
| `EnsureCommissioner` | User is the commissioner of the route's `{league}` |
| `EnsureAdmin` | User has `role = admin` |
| `ThrottleOddsRefresh` | Rate limits the odds refresh endpoint per user |

### Policy-Based Authorization

```php
// LeaguePolicy.php
public function update(User $user, League $league)   // commissioner + pre-season only
public function delete(User $user, League $league)    // commissioner + pre-season only
public function join(User $user, League $league)      // not already member, not full, not started

// SlatePickPolicy.php
public function swap(User $user, SlatePick $pick)     // owns pick, not locked, game not started
```

---

## 8. Background Jobs & Queue Architecture

### Queue Configuration

```php
// config/horizon.php
'environments' => [
    'production' => [
        'draft-workers' => [
            'connection'  => 'redis',
            'queue'       => ['draft-high'],
            'balance'     => 'auto',
            'processes'   => 4,
            'tries'       => 3,
            'timeout'     => 30,
        ],
        'default-workers' => [
            'connection'  => 'redis',
            'queue'       => ['default'],
            'balance'     => 'auto',
            'processes'   => 3,
            'tries'       => 3,
            'timeout'     => 120,
        ],
        'low-workers' => [
            'connection'  => 'redis',
            'queue'       => ['low'],
            'balance'     => 'auto',
            'processes'   => 2,
            'tries'       => 3,
            'timeout'     => 300,
        ],
    ],
],
```

### Job Definitions

| Job | Queue | Trigger | Retry | Timeout |
|-----|-------|---------|-------|---------|
| `DraftAutoPickJob` | `draft-high` | Timer expiry event | 2 | 10s |
| `DraftAdvanceJob` | `draft-high` | After each pick confirmed | 2 | 10s |
| `ResultGradingJob` | `default` | Scheduler (post-game windows) | 3 | 120s |
| `MatchupScoreJob` | `default` | Dispatched by ResultGradingJob | 3 | 60s |
| `StandingsUpdateJob` | `default` | Dispatched by MatchupScoreJob | 3 | 60s |
| `SlateLockJob` | `default` | Scheduler (every 5 min) + game time events | 3 | 60s |
| `SlatePoolBuildJob` | `low` | Scheduler (15-30 min pre-draft) | 3 | 300s |
| `OddsRefreshJob` | `low` | Scheduler (every 2-3 hours) | 3 | 300s |
| `NotificationDispatchJob` | `low` | Dispatched by other jobs | 3 | 60s |
| `PayoutJob` | `low` | Season completion / admin trigger | 3 | 120s |
| `RefundJob` | `low` | League cancellation | 3 | 120s |

### Scheduler (Console Kernel)

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Check for leagues needing slate pool builds (every 5 minutes)
    $schedule->job(new CheckAndDispatchSlatePoolBuilds)->everyFiveMinutes();

    // Lock picks for games at kickoff (every 5 minutes)
    $schedule->job(new SlateLockJob)->everyFiveMinutes();

    // Refresh odds for active slate picks
    $schedule->job(new OddsRefreshJob)
             ->cron('0 */3 * * *')        // every 3 hours
             ->when(fn () => $this->isActiveWeek());

    // Grade results — Sunday night batch
    $schedule->job(new ResultGradingJob)
             ->sundays()->at('23:30')
             ->timezone('America/New_York');

    // Grade results — Monday night batch
    $schedule->job(new ResultGradingJob)
             ->mondays()->at('23:45')
             ->timezone('America/New_York');

    // Cleanup/re-grade — Tuesday morning
    $schedule->job(new ResultGradingJob)
             ->tuesdays()->at('06:00')
             ->timezone('America/New_York');
}
```

### Job Chain Pattern (Result Processing)

```
ResultGradingJob (grades picks for completed games)
  └── dispatches MatchupScoreJob (calculates weekly scores, determines winners)
        └── dispatches StandingsUpdateJob (recalculates standings)
              └── dispatches NotificationDispatchJob (sends result notifications)
```

---

## 9. Odds Integration (the-odds-api.com)

### `OddsApiService.php`

```php
class OddsApiService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.the-odds-api.com/v4';

    // Fetch all NFL player prop markets for the upcoming week's games.
    // Returns structured data: game info, market type, outcomes with odds.
    public function fetchNflPlayerProps(array $markets = []): array;

    // Fetch NFL game lines (moneyline, spread, totals).
    public function fetchNflGameLines(): array;

    // Fetch current odds for specific event IDs (for odds refresh).
    public function fetchOddsForEvents(array $eventIds): array;

    // Check remaining API quota.
    public function getRemainingQuota(): int;
}
```

### Markets We Fetch

```php
// config/draftslate.php
'odds_api' => [
    'key'            => env('ODDS_API_KEY'),
    'sport'          => 'americanfootball_nfl',
    'regions'        => 'us',
    'odds_format'    => 'american',
    'bookmaker'      => env('ODDS_API_PRIMARY_BOOK', 'fanduel'),  // primary bookmaker
    'player_prop_markets' => [
        'player_pass_yds',
        'player_pass_tds',
        'player_rush_yds',
        'player_receptions',
        'player_reception_yds',
        'player_anytime_td',
    ],
    'game_markets' => [
        'h2h',          // moneyline
        'spreads',
        'totals',
    ],
    'refresh_interval_hours' => env('ODDS_API_REFRESH_INTERVAL_HOURS', 3),
],
```

### Slate Pool Build Process

1. `SlatePoolBuildJob` fires 15-30 min before draft
2. Calls `OddsApiService::fetchNflPlayerProps()` + `fetchNflGameLines()`
3. Deduplication check: if another league's build job fetched odds within the last 30 minutes, reuse cached API response
4. Filters picks:
   - Game must be > 24 hours from draft time (excludes imminent Thursday games)
   - Pick must meet the league's odds enforcement rules (Global Floor or pass the widest band)
5. Creates `SlatePool` record + batch-inserts `PickSelection` records
6. Updates `DraftState` to `ready`, broadcasts `SlatePoolReady` event
7. Logs execution to `job_logs`

---

## 10. Real-Time: Broadcasting, WebSockets & Events

### Channel Structure

```php
// routes/channels.php

// Private channel per league draft — all league members can listen
Broadcast::channel('draft.{leagueId}', function (User $user, int $leagueId) {
    return $user->leagues()->where('leagues.id', $leagueId)->exists();
});

// Private channel per league — general league events
Broadcast::channel('league.{leagueId}', function (User $user, int $leagueId) {
    return $user->leagues()->where('leagues.id', $leagueId)->exists();
});

// Private channel per user — personal notifications
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});
```

### Broadcast Events

| Event | Channel | Payload | Trigger |
|-------|---------|---------|---------|
| `SlatePoolReady` | `draft.{leagueId}` | `{ status: 'ready', pickCount }` | Pool build job completes |
| `DraftStarted` | `draft.{leagueId}` | `{ draftOrder, currentDrafter, round }` | Draft begins |
| `DraftPickMade` | `draft.{leagueId}` | `{ pick, drafter, round, pickNumber }` | Manual or auto pick confirmed |
| `DraftAdvanced` | `draft.{leagueId}` | `{ currentDrafter, round, pickIndex, timerStart }` | Next drafter's turn |
| `DraftCompleted` | `draft.{leagueId}` | `{ finalRosters }` | All rounds complete |
| `PickLocked` | `league.{leagueId}` | `{ pickId, gameId }` | Game kicks off |
| `PickGraded` | `league.{leagueId}` | `{ pickId, outcome, gameId }` | Result grading done |
| `MatchupResolved` | `league.{leagueId}` | `{ matchupId, homeScore, awayScore, winnerId }` | Matchup scored |

### Frontend Echo Setup

```js
// src/utils/echo.js
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

const echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  wsHost: import.meta.env.VITE_PUSHER_HOST,        // for Soketi
  wsPort: import.meta.env.VITE_PUSHER_PORT,
  forceTLS: import.meta.env.VITE_PUSHER_SCHEME === 'https',
  enabledTransports: ['ws', 'wss'],
})

export default echo
```

---

## 11. Payment System (Stripe)

### Buy-In Flow

1. User clicks "Join League" → frontend calls `POST /api/v1/leagues/{league}/join`
2. Backend creates a Stripe PaymentIntent for the buy-in amount
3. Returns `client_secret` to frontend
4. Frontend uses Stripe.js to confirm payment (card element)
5. On success: webhook `payment_intent.succeeded` fires → backend creates `LeagueMembership` + `Transaction` record
6. On failure: no membership created, user sees error

### Payout Flow

1. `PayoutJob` fires when league state → `completed`
2. Calculates each winner's share: `(total_pot * 0.90) * payout_percentage`
3. Creates Stripe Transfer to each winner's connected account (or Stripe payout)
4. Records `Transaction` records for each payout + the platform commission
5. Sends notification to each winner

### Commissioner Subscription (Laravel Cashier)

```php
// SubscriptionController.php
public function create(Request $request)
{
    $request->user()->newSubscription('commissioner', env('STRIPE_COMMISSIONER_PRICE_ID'))
        ->create($request->paymentMethodId);
}

public function cancel(Request $request)
{
    $request->user()->subscription('commissioner')->cancel();
}
```

### Stripe Webhooks

| Webhook Event | Action |
|--------------|--------|
| `payment_intent.succeeded` | Create league membership, record buy-in transaction |
| `payment_intent.payment_failed` | Notify user, no membership created |
| `customer.subscription.created` | Activate commissioner subscription |
| `customer.subscription.deleted` | Deactivate subscription, block new league creation |
| `transfer.created` | Update transaction record as completed |
| `charge.refunded` | Update transaction record as refunded |

---

## 12. Frontend: Vue 3 Application Architecture

### Router Configuration

```js
// src/router/index.js
const routes = [
  // Public routes
  { path: '/',           component: MarketingHome,  meta: { guest: true } },
  { path: '/login',      component: LoginPage,      meta: { guest: true } },
  { path: '/register',   component: RegisterPage,   meta: { guest: true } },

  // Authenticated routes (AppLayout wrapper)
  {
    path: '/app',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '',                    redirect: '/app/dashboard' },
      { path: 'dashboard',          component: DashboardPage },
      { path: 'leagues',            component: LeaguesPage },
      { path: 'leagues/browse',     component: LeagueBrowsePage },
      { path: 'leagues/create',     component: LeagueCreatePage },
      { path: 'leagues/:id',        component: LeagueViewPage, props: true },
      { path: 'leagues/:id/draft',  component: DraftPage,      props: true },
      { path: 'feed',               component: FeedPage },
      { path: 'profile',            component: ProfilePage },
      { path: 'settings',           component: SettingsPage },
    ],
  },

  { path: '/:pathMatch(.*)*', component: NotFoundPage },
]
```

### Auth Guard

```js
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return '/app/dashboard'
  }
})
```

### Pinia Store Structure

#### `auth.js`

```js
export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const initialized = ref(false)

  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isSubscribed = computed(() => user.value?.subscription_active)

  async function fetchUser() { /* GET /api/v1/auth/user */ }
  async function login(email, password) { /* POST /api/v1/auth/login */ }
  async function register(data) { /* POST /api/v1/auth/register */ }
  async function logout() { /* POST /api/v1/auth/logout */ }

  return { user, initialized, isAuthenticated, isAdmin, isSubscribed, fetchUser, login, register, logout }
})
```

#### `draft.js`

```js
export const useDraftStore = defineStore('draft', () => {
  const draftState = ref(null)           // current draft state object
  const availablePicks = ref([])          // remaining picks in pool
  const myRoster = ref([])               // user's drafted picks this session
  const allRosters = ref({})             // all teams' rosters (membership_id => picks[])
  const isMyTurn = ref(false)
  const timerSeconds = ref(0)

  // Actions
  async function loadDraft(leagueId) { /* GET /api/v1/leagues/{id}/draft */ }
  async function loadPool(leagueId) { /* GET /api/v1/leagues/{id}/draft/pool */ }
  async function submitPick(leagueId, pickSelectionId, slotNumber) { /* POST */ }

  // WebSocket listeners setup
  function subscribeToDraftChannel(leagueId) {
    echo.private(`draft.${leagueId}`)
      .listen('DraftPickMade', handlePickMade)
      .listen('DraftAdvanced', handleDraftAdvanced)
      .listen('DraftCompleted', handleDraftCompleted)
      .listen('SlatePoolReady', handlePoolReady)
  }

  return { draftState, availablePicks, myRoster, allRosters, isMyTurn, timerSeconds,
           loadDraft, loadPool, submitPick, subscribeToDraftChannel }
})
```

#### `slate.js`

```js
export const useSlateStore = defineStore('slate', () => {
  const starters = ref([])               // starter slate picks for current week
  const bench = ref([])                  // bench slate picks for current week
  const loading = ref(false)

  async function loadSlate(leagueId, week) { /* GET /api/v1/leagues/{id}/slate/{week} */ }
  async function swapPick(leagueId, pickId, targetPosition, targetSlot) { /* POST */ }
  async function refreshOdds(leagueId) { /* POST (rate-limited) */ }

  return { starters, bench, loading, loadSlate, swapPick, refreshOdds }
})
```

### Axios Instance

```js
// src/utils/api.js
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

// Automatically handle CSRF
api.interceptors.request.use(async (config) => {
  if (['post', 'put', 'delete', 'patch'].includes(config.method)) {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
  }
  return config
})

// Handle 401 → redirect to login
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
```

---

## 13. Frontend: Views, Components & User Flows

### Marketing Homepage (Unauthenticated)

- Full-width hero with animated gradient background (violet → deep blue)
- Headline: "Draft Your Predictions. Dominate Your League."
- Two CTAs: "Create Account" (primary, `ds-primary`) and "Log In" (ghost button)
- Feature highlight cards with subtle entrance animations (fade-up on scroll)
- No bottom nav, no app chrome — standalone page
- Mobile: stacked layout, CTA buttons full-width

### App Shell (AppLayout.vue)

```
┌─────────────────────────────────────────────┐
│  [View Title]                    [Avatar ▾] │ ← AppTopBar
├─────────────────────────────────────────────┤
│                                             │
│            Page Content                     │ ← <router-view>
│            (scrollable)                     │
│                                             │
├─────────────────────────────────────────────┤
│  [Dashboard]    [Leagues]    [Feed]         │ ← AppBottomNav
└─────────────────────────────────────────────┘
```

- Bottom nav: 3 items, icon + small label, fixed to bottom
- Active state: filled icon + `ds-primary` tint
- Top bar: minimal — view title left, avatar right (opens profile/settings dropdown)
- Tablet: bottom nav optionally becomes left rail

### Dashboard Page

```
┌─────────────────────────────────────────────┐
│  ⚡ Draft tonight at 8pm — NFL Kings League │ ← AlertBanner (conditional)
├─────────────────────────────────────────────┤
│  My Leagues                                 │
│  ┌────────┐ ┌────────┐ ┌────────┐          │ ← LeagueCardRow (horizontal scroll)
│  │League 1│ │League 2│ │League 3│          │
│  │3-1-0   │ │5-2-0   │ │1-4-0   │          │
│  │Draft   │ │Manage  │ │Results │          │
│  │Tonight │ │Lineup  │ │In      │          │
│  └────────┘ └────────┘ └────────┘          │
├─────────────────────────────────────────────┤
│  ┌──────────────┐ ┌──────────────┐          │
│  │ Join a League│ │Create League │          │ ← Action CTAs
│  └──────────────┘ └──────────────┘          │
├─────────────────────────────────────────────┤
│  Recent Activity                     See all│ ← FeedPreview
│  ─ You won 4-2 vs TeamX in LeagueA         │
│  ─ Chase 6+ receptions HIT (+110)          │
│  ─ Draft tomorrow at 8pm in LeagueB        │
├─────────────────────────────────────────────┤
│  Career: 14-8-0 · 62% hit rate · 🏆 1     │ ← QuickStats
└─────────────────────────────────────────────┘
```

### League View Page (Core Gameplay)

Three tabs with swipeable content:

**Tab 1 — My Picks:**
- Status line: "3 of 5 starters locked · 2 unlocked picks remaining"
- Starter cards (5 stacked vertically)
- Bench cards (3 stacked, visually muted)
- Tap card → PickDetailSheet (bottom sheet with swap actions)

**Tab 2 — Matchup:**
- Score header: "You 3 — 2 Opponent" (animated counter)
- Side-by-side (desktop) or stacked (mobile) pick comparison
- HIT/MISS/PENDING badges per pick
- Aggregate odds probability strip at bottom

**Tab 3 — Standings:**
- Ranked team list with W/L/T, correct picks, last week badge
- Tap row → TeamDetailPopup
- Week selector dropdown for historical snapshots
- Playoff mode: transforms to bracket view with toggle back to regular season

### Draft Page

The highest-fidelity, most animated screen in the app.

```
┌─────────────────────────────────────────────┐
│  ⏱ 0:47                          Round 3/8 │ ← DraftTimer (sticky, pulsing)
│  TeamName's Pick                            │ ← Current drafter highlight
├─────────────────────────────────────────────┤
│  Available Picks          [Filter ▾] [Sort] │
│  ┌──────────────────────────────────────┐   │
│  │ Mahomes 280+ pass yds  -160  KC@LV  │   │ ← AvailablePicksList
│  │ Chase 6+ receptions    +110  CIN@PIT│   │
│  │ Bills win by 7+        -130  BUF@NYJ│   │
│  │ Barkley 80+ rush yds   -200  PHI@DAL│   │
│  │ ... (scrollable)                     │   │
│  └──────────────────────────────────────┘   │
├─────────────────────────────────────────────┤
│  My Roster                                  │ ← DraftRosterPanel (collapsible)
│  S1: Mahomes 280+ yds ✓                     │
│  S2: (empty)                                │
│  S3: (empty)                                │
│  B1: (empty)                                │
└─────────────────────────────────────────────┘
```

- Timer < 10s: pulses red, frequency increases
- Pick selection: tap → DraftPickConfirm bottom sheet
- Confirmed pick: card animates (lifts, glows, flies to roster)
- Auto-pick: AutoPickNotice toast slides in
- Opponent picks: appear in real-time via WebSocket, brief highlight animation
- Draft complete: celebration animation, transition to slate management

### League Creation Wizard

6-step form with progress indicator:

1. **Basics:** League name, public/private, max teams (even number selector)
2. **Buy-In:** Amount input (min $5), payout structure (preset or custom splits)
3. **Roster & Odds:** Starter/bench count, odds mode toggle (Global Floor / Per-Slot Bands), odds config
4. **Draft:** Draft day picker, time picker, timezone, pick timer duration
5. **Season:** Regular season week count, playoff format selector (A/B/C/D with visual bracket preview)
6. **Review:** Summary of all settings with edit links, "Create League" CTA

Vuelidate validates each step inline. Step-to-step transitions use horizontal slide animation.

---

## 14. Notifications

### Notification Types & Channels

| Type | Push | Email | In-App |
|------|------|-------|--------|
| Draft starting (24h, 1h, 15m before) | Yes | Yes (24h only) | Yes |
| Your turn to pick | Yes | No | Yes |
| Auto-pick made on your behalf | Yes | No | Yes |
| Pick locked (game starting) | Yes | No | Yes |
| Weekly results posted | Yes | Yes | Yes |
| Matchup win/loss | Yes | No | Yes |
| Season standings updated | No | No | Yes |
| League invite received | Yes | Yes | Yes |
| Payout processed | Yes | Yes | Yes |

### Laravel Notification Classes

Each notification class implements `ShouldQueue` and defines `via()`, `toMail()`, `toArray()` (for database/in-app), and `toFcm()` (for push).

```php
// Example: DraftReminder.php
class DraftReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public League $league,
        public string $timing  // '24h', '1h', '15m'
    ) {}

    public function via($notifiable): array
    {
        return $this->timing === '24h'
            ? ['mail', 'database', 'fcm']
            : ['database', 'fcm'];
    }

    public function toMail($notifiable): MailMessage { /* ... */ }
    public function toArray($notifiable): array { /* ... */ }
}
```

---

## 15. Rate Limiting & Throttling

### Platform Rate Limits

```php
// config/draftslate.php
'rate_limits' => [
    'odds_refresh_cooldown_minutes' => env('USER_ODDS_REFRESH_COOLDOWN_MINUTES', 10),
    'lineup_swap_limit_per_hour'    => env('USER_LINEUP_SWAP_LIMIT_PER_HOUR', 20),
    'api_general_per_minute'        => env('API_GENERAL_THROTTLE_PER_MINUTE', 60),
],
```

### Implementation

```php
// RouteServiceProvider.php or bootstrap/app.php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(config('draftslate.rate_limits.api_general_per_minute'))
        ->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('odds-refresh', function (Request $request) {
    return Limit::perMinutes(
        config('draftslate.rate_limits.odds_refresh_cooldown_minutes'),
        1
    )->by($request->user()->id);
});

RateLimiter::for('lineup-swap', function (Request $request) {
    return Limit::perHour(
        config('draftslate.rate_limits.lineup_swap_limit_per_hour')
    )->by($request->user()->id);
});
```

### the-odds-api.com Quota Management

- All API calls go through `OddsApiService` which tracks quota usage
- "Fetch once, fan out" — single fetch cached for all leagues within a 30-min window
- Quota remaining logged after each call; alerts admin if below threshold
- Refresh interval configurable via `ODDS_API_REFRESH_INTERVAL_HOURS` env var

---

## 16. Testing Strategy

### Backend Tests (PHPUnit)

#### Unit Tests

| Test Suite | Coverage |
|-----------|----------|
| `OddsMathServiceTest` | Odds conversion, floor checking, band validation, drift calculation |
| `DraftOrderServiceTest` | Weighted randomization, snake sequence generation, Week 1 equal weights |
| `ScoringServiceTest` | Pick grading (hit/miss/void), matchup scoring, tiebreakers |
| `StandingsServiceTest` | Standings calculation, tiebreaker ordering (5 levels) |
| `PayoutServiceTest` | Payout split calculation, commission deduction |

#### Feature Tests

| Test Suite | Coverage |
|-----------|----------|
| `AuthTest` | Register, login, logout, Google OAuth flow, password reset |
| `LeagueTest` | Create, join, leave, cancel, settings lock after season start |
| `DraftTest` | Pick submission, auto-pick, timer expiry, snake order, odds validation |
| `SlateManagementTest` | Swap positions, lock enforcement, per-game lock timing |
| `MatchupTest` | Scoring, winner determination, tie handling |
| `PaymentTest` | Buy-in flow, payout processing, refund on cancellation |
| `RateLimitTest` | Odds refresh cooldown, swap limits, general API throttle |

### Frontend Tests (Vitest + Vue Test Utils)

| Test Suite | Coverage |
|-----------|----------|
| `DraftTimer.spec.js` | Countdown accuracy, color changes at thresholds, pulse at < 10s |
| `SlatePickCard.spec.js` | All card states (pending, hit, miss, locked), odds display |
| `OddsHelpers.spec.js` | Odds formatting, delta calculation, floor checking |
| `DraftStore.spec.js` | WebSocket event handling, pick submission, roster state |
| `AuthStore.spec.js` | Login/logout flow, session persistence |

---

## 17. Deployment & Infrastructure

### Deployment Target: Existing DigitalOcean Server

DraftSlate deploys to an existing DigitalOcean Droplet that is already provisioned. The server stack runs all application services on a single Droplet with external managed services for storage and third-party integrations.

```
┌────────────────────────────────────────────┐
│        Existing DigitalOcean Droplet       │
│  ┌──────────────────────────────────────┐  │
│  │  Nginx                               │  │
│  │  ├── serves Vue SPA (static build)  │  │
│  │  └── proxies /api → PHP-FPM         │  │
│  ├──────────────────────────────────────┤  │
│  │  PHP 8.2+ / PHP-FPM (Laravel)       │  │
│  ├──────────────────────────────────────┤  │
│  │  MySQL 8.0+ (local or DO managed)   │  │
│  ├──────────────────────────────────────┤  │
│  │  Redis (local service)               │  │
│  ├──────────────────────────────────────┤  │
│  │  Laravel Horizon (supervisor)        │  │
│  ├──────────────────────────────────────┤  │
│  │  Soketi (local WebSocket server)     │  │
│  └──────────────────────────────────────┘  │
└────────────────────────────────────────────┘
         │            │            │
         ▼            ▼            ▼
   DO Spaces      Mailgun       Stripe
   (assets)       (email)       (payments)
```

### Server Prerequisites

Verify these are installed/available on the existing Droplet. Install any that are missing:

```bash
# PHP 8.2+ with required extensions
php -v
php -m | grep -E "mbstring|xml|curl|mysql|redis|bcmath|gd|zip"

# If PHP 8.2 is not installed:
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.2 php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis \
  php8.2-mbstring php8.2-xml php8.2-curl php8.2-bcmath php8.2-gd php8.2-zip

# Composer
composer --version
# If missing: https://getcomposer.org/download/

# Node.js (for frontend builds — 18+ recommended)
node -v
# If missing:
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# MySQL 8.0+
mysql --version
# If using local MySQL, create the database:
sudo mysql -e "CREATE DATABASE draftslate; CREATE USER 'draftslate'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD'; GRANT ALL ON draftslate.* TO 'draftslate'@'localhost'; FLUSH PRIVILEGES;"

# Redis
redis-cli ping
# If missing:
sudo apt install redis-server
sudo systemctl enable redis-server

# Nginx (likely already present)
nginx -v

# Supervisor (for Horizon + Soketi process management)
supervisorctl --version
# If missing:
sudo apt install supervisor
```

### Soketi (WebSocket Server) Setup

```bash
# Install Soketi globally
sudo npm install -g @soketi/soketi

# Create Soketi config
# /etc/soketi/config.json
{
  "port": 6001,
  "appManager.array.apps": [
    {
      "id": "draftslate",
      "key": "draftslate-key",
      "secret": "draftslate-secret",
      "maxConnections": -1,
      "enableClientMessages": false,
      "maxBackendEventsPerSecond": -1,
      "maxClientEventsPerSecond": -1
    }
  ]
}
```

### Supervisor Configuration

```ini
# /etc/supervisor/conf.d/draftslate-horizon.conf
[program:draftslate-horizon]
process_name=%(program_name)s
command=php /var/www/draftslate/backend/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/draftslate/backend/storage/logs/horizon.log
stopwaitsecs=3600

# /etc/supervisor/conf.d/draftslate-soketi.conf
[program:draftslate-soketi]
process_name=%(program_name)s
command=soketi start --config=/etc/soketi/config.json
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/soketi.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start draftslate-horizon
sudo supervisorctl start draftslate-soketi
```

### Project Directory Setup

```bash
# Create project directory (adjust path to match your server conventions)
sudo mkdir -p /var/www/draftslate
sudo chown $USER:www-data /var/www/draftslate

# Clone or copy project files
# Option A: Git clone
cd /var/www/draftslate
git clone <your-repo-url> .

# Option B: rsync from local machine
rsync -avz --exclude node_modules --exclude vendor \
  ~/Documents/my\ projects/draftSlate/ \
  user@your-droplet-ip:/var/www/draftslate/
```

### Build & Deploy Steps

```bash
# --- Frontend build ---
cd /var/www/draftslate/frontend
npm ci
npm run build
# Copy build output into Laravel's public directory
cp -r dist/* /var/www/draftslate/backend/public/

# --- Backend setup ---
cd /var/www/draftslate/backend
composer install --optimize-autoloader --no-dev

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Environment
cp .env.example .env
# Edit .env with production values (see Section 18)
php artisan key:generate

# Cache config & routes
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Seed platform settings
php artisan db:seed --class=PlatformSettingsSeeder

# Restart Horizon workers
php artisan horizon:terminate

# Set up Laravel scheduler cron
# Add to crontab (crontab -e):
# * * * * * cd /var/www/draftslate/backend && php artisan schedule:run >> /dev/null 2>&1
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name draftslate.com www.draftslate.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name draftslate.com www.draftslate.com;

    # SSL (use certbot/Let's Encrypt or your existing certs)
    ssl_certificate /etc/letsencrypt/live/draftslate.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/draftslate.com/privkey.pem;

    root /var/www/draftslate/backend/public;
    index index.php index.html;

    charset utf-8;
    client_max_body_size 10M;

    # API requests → Laravel
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Sanctum CSRF cookie
    location /sanctum {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Broadcasting auth
    location /broadcasting {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Horizon dashboard (admin only — Laravel gate handles auth)
    location /horizon {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # WebSocket proxy to Soketi
    location /app {
        proxy_pass http://127.0.0.1:6001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 86400;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Static assets with cache headers
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2|woff|ttf)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Deny hidden files
    location ~ /\. {
        deny all;
    }

    # SPA fallback — all other routes serve index.html
    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

```bash
# Enable the site and reload
sudo ln -sf /etc/nginx/sites-available/draftslate /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# SSL via Let's Encrypt (if not already configured)
sudo certbot --nginx -d draftslate.com -d www.draftslate.com
```

### Redeployment Script

Create a deploy script on the server to streamline future deploys:

```bash
#!/bin/bash
# /var/www/draftslate/deploy.sh
set -e

echo "=== Pulling latest code ==="
cd /var/www/draftslate
git pull origin main

echo "=== Building frontend ==="
cd frontend
npm ci
npm run build
cp -r dist/* ../backend/public/

echo "=== Updating backend ==="
cd ../backend
composer install --optimize-autoloader --no-dev

echo "=== Caching ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== Running migrations ==="
php artisan migrate --force

echo "=== Restarting workers ==="
php artisan horizon:terminate
sudo supervisorctl restart draftslate-soketi

echo "=== Done ==="
```

```bash
chmod +x /var/www/draftslate/deploy.sh
```

---

## 18. Environment Variables Reference

```bash
# === Application ===
APP_NAME=DraftSlate
APP_ENV=production
APP_KEY=base64:...
APP_URL=https://draftslate.com

# === Database ===
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=draftslate
DB_USERNAME=draftslate
DB_PASSWORD=...

# === Redis ===
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# === Queue ===
QUEUE_CONNECTION=redis

# === Session ===
SESSION_DRIVER=redis
SESSION_DOMAIN=draftslate.com

# === Sanctum ===
SANCTUM_STATEFUL_DOMAINS=draftslate.com

# === Broadcasting ===
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_HOST=127.0.0.1           # for Soketi
PUSHER_PORT=6001
PUSHER_SCHEME=http

# === Stripe ===
STRIPE_KEY=pk_...
STRIPE_SECRET=sk_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_COMMISSIONER_PRICE_ID=price_...

# === Google OAuth ===
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URL=https://draftslate.com/api/v1/auth/google/callback

# === the-odds-api.com ===
ODDS_API_KEY=...
ODDS_API_REFRESH_INTERVAL_HOURS=3
ODDS_API_PRIMARY_BOOK=fanduel

# === Mail ===
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=...
MAILGUN_SECRET=...

# === Storage ===
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=draftslate-assets

# === Rate Limits ===
USER_ODDS_REFRESH_COOLDOWN_MINUTES=10
USER_LINEUP_SWAP_LIMIT_PER_HOUR=20
API_GENERAL_THROTTLE_PER_MINUTE=60

# === Frontend (Vite .env) ===
VITE_API_URL=/api/v1
VITE_PUSHER_APP_KEY=...
VITE_PUSHER_APP_CLUSTER=mt1
VITE_PUSHER_HOST=127.0.0.1
VITE_PUSHER_PORT=6001
VITE_PUSHER_SCHEME=http
VITE_STRIPE_KEY=pk_...
```

---

## 19. Implementation Phases & Build Order

### Phase 1 — Foundation (Weeks 1-2)

**Goal:** Scaffolded project, auth working, database migrations in place.

- [ ] Create Laravel project, install all packages
- [ ] Create Vue project, install all packages, configure Tailwind with full design token system
- [ ] Write all database migrations, run them
- [ ] Implement User model with Sanctum auth (register, login, logout)
- [ ] Implement Google OAuth via Socialite
- [ ] Build auth pages (LoginPage, RegisterPage) with Vuelidate
- [ ] Build AppLayout (top bar, bottom nav) with design system styles
- [ ] Set up Axios instance with CSRF handling
- [ ] Set up Redis locally, configure Horizon, verify queue processing
- [ ] Build MarketingHome page

**Deliverable:** User can register, log in (email or Google), see the app shell with empty dashboard.

### Phase 2 — Leagues (Weeks 3-4)

**Goal:** Full league creation, browsing, joining, and payment flow.

- [ ] Build League model, LeagueMembership model, all relationships
- [ ] Build LeagueController — create, browse, join, leave, cancel
- [ ] Implement league creation wizard (6-step form with validation)
- [ ] Implement Stripe buy-in payment flow (PaymentIntent, webhooks)
- [ ] Build DashboardPage with league card row, alert banner, quick stats
- [ ] Build LeaguesPage (active + completed leagues list)
- [ ] Build LeagueBrowsePage (public league browser with filters)
- [ ] Implement invite code system for private leagues
- [ ] Implement commissioner subscription (Laravel Cashier)
- [ ] Write league business rule enforcement (even teams, settings lock, etc.)

**Deliverable:** Users can create leagues, set all config options, join via browse or invite, pay buy-ins.

### Phase 3 — Odds & Slate Pool (Weeks 5-6)

**Goal:** Odds API integration working, slate pools being built.

- [ ] Build OddsApiService (fetch player props, game lines, quota tracking)
- [ ] Build OddsMathService (conversions, floor checks, band checks, drift)
- [ ] Build SlatePool, PickSelection models
- [ ] Build SlatePoolBuildJob (fetch, filter, create pool records, deduplication)
- [ ] Implement scheduler check for leagues needing pool builds
- [ ] Build config/draftslate.php with all configurable values
- [ ] Build PlatformSetting model + seeder
- [ ] Test slate pool creation with real the-odds-api.com data

**Deliverable:** Slate pools are automatically built before scheduled draft times with real NFL odds data.

### Phase 4 — The Draft (Weeks 7-9)

**Goal:** Full live draft experience — the most complex feature.

- [ ] Build DraftState model, DraftService, DraftOrderService
- [ ] Implement weighted random draft order generation
- [ ] Implement snake draft sequence logic
- [ ] Build DraftController (load state, submit pick, get pool, get order)
- [ ] Build DraftAutoPickJob and DraftAdvanceJob
- [ ] Set up Laravel Broadcasting with Soketi
- [ ] Build all draft broadcast events
- [ ] Build frontend DraftPage with all sub-components:
  - DraftLobby (preparing state)
  - DraftTimer (countdown with pulse animations)
  - AvailablePicksList (filterable, sortable)
  - DraftRosterPanel (live roster build)
  - DraftPickConfirm (bottom sheet confirmation)
  - DraftOrderDisplay (order + weights transparency)
  - AutoPickNotice (toast)
- [ ] Build useDraft composable and draft Pinia store
- [ ] Implement WebSocket listeners for all draft events
- [ ] Implement odds validation (Global Floor + Per-Slot Bands modes)
- [ ] Build draft pick card animations (selection, confirmation, opponent picks)
- [ ] Test full draft flow with multiple browser windows

**Deliverable:** Complete live draft experience with real-time updates, auto-pick, timer, animations.

### Phase 5 — Slate Management & Scoring (Weeks 10-12)

**Goal:** Post-draft lineup management, game locking, result grading.

- [ ] Build SlatePick model, SlateManagementService
- [ ] Build SlateController (load slate, swap, refresh odds)
- [ ] Implement per-game lock logic (SlateLockJob)
- [ ] Build OddsRefreshJob
- [ ] Build LeagueViewPage with three tabs:
  - MyPicksTab (starter/bench cards, swap actions, lock countdowns)
  - MatchupTab (head-to-head comparison, live score)
  - StandingsTab (ranked list, week selector, team detail popup)
- [ ] Build PickDetailSheet (bottom sheet with full details + swap actions)
- [ ] Build SlatePickCard with all states (pending, locked, hit, miss)
- [ ] Implement HIT/MISS result animations
- [ ] Build ResultGradingJob (fetch game results, grade picks)
- [ ] Build MatchupScoreJob (calculate scores, determine winners)
- [ ] Build StandingsUpdateJob (recalculate standings, tiebreakers)
- [ ] Build ScoringService with tiebreaker logic (5 levels)
- [ ] Build MatchupSchedulerService (round-robin, minimize repeat matchups)
- [ ] Implement matchup result notifications and feed items
- [ ] Build odds drift tracking (drafted_odds → locked_odds capture)

**Deliverable:** Full weekly gameplay loop — draft → manage → lock → grade → score → standings.

### Phase 6 — Playoffs & Season Completion (Weeks 13-14)

**Goal:** Playoff brackets, championship, payouts.

- [ ] Implement playoff bracket generation (Formats A, B, C, D)
- [ ] Implement playoff seeding with tiebreakers
- [ ] Build PlayoffBracket component (visual bracket view)
- [ ] Implement playoff draft order (only active teams, weighted)
- [ ] Implement season state transitions (active → playoffs → completed)
- [ ] Build PayoutJob (Stripe transfers, commission deduction)
- [ ] Build RefundJob (league cancellation refunds)
- [ ] Implement league completion flow (champion determination → payout trigger)
- [ ] Build payout notifications

**Deliverable:** Full season lifecycle from start through championship and payout.

### Phase 7 — Profile, Feed & Polish (Weeks 15-16)

**Goal:** Career stats, activity feed, notifications, final UX polish.

- [ ] Build ProfilePage with career stats, badges, odds drift metrics
- [ ] Build CareerStats component (hit rate, record, streaks)
- [ ] Build OddsDriftChart component (Draft Value / Market IQ display)
- [ ] Build BadgeGrid component (championship badges)
- [ ] Build FeedPage with league-scoped feed items and filters
- [ ] Implement FeedItem creation across all relevant events
- [ ] Implement push notifications (FCM integration)
- [ ] Implement email notifications (Mailgun)
- [ ] Build notification preferences in SettingsPage
- [ ] Implement financial history (private, user-only view)
- [ ] Implement dark/light mode toggle (system preference detection)
- [ ] Accessibility pass (reduced motion, focus states, screen reader labels)
- [ ] Performance audit (code splitting, lazy loading, image optimization)
- [ ] Cross-browser/device testing

**Deliverable:** Complete MVP ready for beta testing.

### Phase 8 — Testing & Launch Prep (Weeks 17-18)

- [ ] Write comprehensive backend test suite (unit + feature)
- [ ] Write frontend component tests
- [ ] End-to-end testing of full season lifecycle
- [ ] Security audit (OWASP top 10, Stripe webhook verification, CSRF)
- [ ] Load testing (concurrent draft sessions, Sunday night result grading)
- [ ] Set up production infrastructure (Forge, managed MySQL, Redis, S3)
- [ ] Configure production environment variables
- [ ] Set up monitoring and alerting (Horizon dashboard, error tracking)
- [ ] Legal compliance review coordination
- [ ] Deploy to production

**Deliverable:** Production-ready DraftSlate platform.

---

## Key Business Rules (Quick Reference)

1. League team count must be even (4-14)
2. No joining a league after Week 1 draft starts
3. Commissioner cannot change buy-in/payout/roster after season begins
4. Payout percentages must sum to exactly 100%
5. Each pick in a slate pool can only be drafted by one team
6. Odds enforcement uses snapshot odds (pool creation time), not live odds during draft
7. Pick locks are enforced server-side at game kickoff time
8. Picks lock independently per game, not at a universal weekly time
9. Auto-pick selects highest-odds eligible pick above threshold; ties broken by earliest game time
10. Ghost teams (user left after season start) use auto-pick, no refund
11. Platform 10% commission is non-negotiable
12. Refunds within 24 hours of league cancellation
13. All financial transactions are immutable (corrections via compensating transactions)
14. Draft auto-advance only after previous pick is fully committed (database-level locking)
15. Users limited to 10 simultaneous leagues (configurable)

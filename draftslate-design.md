# DraftSlate — Color Palette Reference

This document defines the official color palette for the DraftSlate platform,
covering both dark mode (primary/default) and light mode. All values are
provided as hex codes with their intended semantic role, Tailwind CSS custom
token names, and CSS variable equivalents for use across the Vue 3 frontend
and any design tooling.

---

## Design Philosophy

DraftSlate uses a **charcoal & teal** palette. The dark charcoal base
establishes a premium, focused feel appropriate for a real-money competitive
platform. The teal accent communicates precision and intelligence — consistent
with the "market reader" skill narrative at the core of the product — while
remaining completely distinct from both DraftKings (green/black) and Underdog
(orange/dark).

The palette is intentionally restrained: two teal stops for the brand accent,
one green for success, one red for failure, and a full neutral gray ramp for
surfaces and text. No additional accent colors are introduced.

---

## Dark Mode (Default)

Dark mode is the primary experience. All component design should be built
dark-first.

### Surface Colors

| Token Name              | Hex       | Role                                              |
|-------------------------|-----------|---------------------------------------------------|
| `ds-bg-primary`         | `#18181B` | App background — outermost layer                  |
| `ds-bg-surface`         | `#27272A` | Cards, modals, sheet backgrounds                  |
| `ds-bg-surface-raised`  | `#3F3F46` | Elevated cards, hover states, locked/muted slots  |
| `ds-bg-teal-deep`       | `#134E4A` | Teal-tinted surface — starter badge bg, active states |
| `ds-bg-teal-wash`       | `#0D2320` | Very dark teal wash — subtle teal-tinted panels   |

### Brand Accent — Teal

| Token Name              | Hex       | Role                                              |
|-------------------------|-----------|---------------------------------------------------|
| `ds-teal-primary`       | `#0D9488` | Primary actions, CTAs, active nav, filled badges  |
| `ds-teal-highlight`     | `#2DD4BF` | Text accents on dark surfaces, odds display, links, current odds value |

### Text Colors

| Token Name              | Hex       | Role                                              |
|-------------------------|-----------|---------------------------------------------------|
| `ds-text-primary`       | `#FAFAFA` | Primary body text, card titles                    |
| `ds-text-secondary`     | `#A1A1AA` | Supporting text, subtitles, timestamps, labels    |
| `ds-text-tertiary`      | `#71717A` | Placeholder text, disabled states, fine print     |
| `ds-text-teal`          | `#2DD4BF` | Teal-accented text on dark backgrounds            |

### Border Colors

| Token Name              | Hex       | Role                                              |
|-------------------------|-----------|---------------------------------------------------|
| `ds-border-default`     | `#3F3F46` | Default card and surface borders                  |
| `ds-border-teal`        | `#0D9488` | Teal-accented borders — active cards, focused inputs |
| `ds-border-subtle`      | `#27272A` | Very subtle dividers within surfaces              |

### Semantic / State Colors

| Token Name              | Hex       | Role                                              |
|-------------------------|-----------|---------------------------------------------------|
| `ds-success`            | `#22C55E` | HIT outcome, positive odds drift, win result      |
| `ds-success-bg`         | `#052E16` | Success badge background on dark surfaces         |
| `ds-danger`             | `#EF4444` | MISS outcome, negative odds drift, loss result    |
| `ds-danger-bg`          | `#3F0A0A` | Danger badge background on dark surfaces          |
| `ds-warning`            | `#F59E0B` | Caution states — pick locking soon, draft warning |
| `ds-warning-bg`         | `#2D1A00` | Warning badge background on dark surfaces         |
| `ds-pending`            | `#A1A1AA` | Pending/awaiting result state                     |
| `ds-locked`             | `#3F3F46` | Locked pick slot — muted, no action available     |

### Dark Mode — Full Reference Sheet

```
Background:
  App bg          #18181B    ds-bg-primary
  Card / surface  #27272A    ds-bg-surface
  Raised surface  #3F3F46    ds-bg-surface-raised
  Teal deep       #134E4A    ds-bg-teal-deep
  Teal wash       #0D2320    ds-bg-teal-wash

Brand:
  Teal primary    #0D9488    ds-teal-primary
  Teal highlight  #2DD4BF    ds-teal-highlight

Text:
  Primary         #FAFAFA    ds-text-primary
  Secondary       #A1A1AA    ds-text-secondary
  Tertiary        #71717A    ds-text-tertiary
  Teal text       #2DD4BF    ds-text-teal

Borders:
  Default         #3F3F46    ds-border-default
  Teal            #0D9488    ds-border-teal
  Subtle          #27272A    ds-border-subtle

States:
  Success         #22C55E    ds-success
  Success bg      #052E16    ds-success-bg
  Danger          #EF4444    ds-danger
  Danger bg       #3F0A0A    ds-danger-bg
  Warning         #F59E0B    ds-warning
  Warning bg      #2D1A00    ds-warning-bg
  Pending         #A1A1AA    ds-pending
  Locked          #3F3F46    ds-locked
```

---

## Light Mode

Light mode uses the same brand teal values — `#0D9488` and `#2DD4BF` are
unchanged. Only surfaces, backgrounds, borders, and text colors invert.
The semantic success/danger colors shift to slightly deeper values for
sufficient contrast on white surfaces.

### Surface Colors

| Token Name              | Hex       | Role                                              |
|-------------------------|-----------|---------------------------------------------------|
| `ds-bg-primary`         | `#E8ECED` | App background — outermost layer                  |
| `ds-bg-surface`         | `#FFFFFF` | Cards, modals, sheet backgrounds                  |
| `ds-bg-surface-raised`  | `#F3F5F6` | Subtle raised state, hover, secondary surfaces    |
| `ds-bg-teal-deep`       | `#CCFBF1` | Teal-tinted light fill — starter badges, active highlights |
| `ds-bg-teal-wash`       | `#E6FAF8` | Very light teal wash — tinted panel backgrounds   |

### Brand Accent — Teal (unchanged from dark mode)

| Token Name              | Hex       | Role                                              |
|-------------------------|-----------|---------------------------------------------------|
| `ds-teal-primary`       | `#0D9488` | Primary actions, CTAs, active nav, filled badges  |
| `ds-teal-highlight`     | `#2DD4BF` | Secondary teal accent — highlight text, indicators |

### Text Colors

| Token Name              | Hex       | Role                                              |
|-------------------------|-----------|---------------------------------------------------|
| `ds-text-primary`       | `#18181B` | Primary body text, card titles                    |
| `ds-text-secondary`     | `#6B7280` | Supporting text, subtitles, timestamps, labels    |
| `ds-text-tertiary`      | `#9CA3AF` | Placeholder text, disabled states, fine print     |
| `ds-text-teal`          | `#0D9488` | Teal-accented text on light backgrounds           |

### Border Colors

| Token Name              | Hex       | Role                                              |
|-------------------------|-----------|---------------------------------------------------|
| `ds-border-default`     | `#C8CDD5` | Default card and surface borders                  |
| `ds-border-teal`        | `#0D9488` | Teal-accented borders — active cards, focused inputs |
| `ds-border-subtle`      | `#D1D5DB` | Very subtle inner dividers                        |

### Semantic / State Colors

| Token Name              | Hex       | Role                                              |
|-------------------------|-----------|---------------------------------------------------|
| `ds-success`            | `#16A34A` | HIT outcome, positive odds drift, win result      |
| `ds-success-bg`         | `#DCFCE7` | Success badge background on light surfaces        |
| `ds-danger`             | `#DC2626` | MISS outcome, negative odds drift, loss result    |
| `ds-danger-bg`          | `#FEE2E2` | Danger badge background on light surfaces         |
| `ds-warning`            | `#D97706` | Caution states — pick locking soon, draft warning |
| `ds-warning-bg`         | `#FEF3C7` | Warning badge background on light surfaces        |
| `ds-pending`            | `#9CA3AF` | Pending/awaiting result state                     |
| `ds-locked`             | `#E4E4E7` | Locked pick slot — muted, no action available     |

### Light Mode — Full Reference Sheet

```
Background:
  App bg          #E8ECED    ds-bg-primary
  Card / surface  #FFFFFF    ds-bg-surface
  Raised surface  #F3F5F6    ds-bg-surface-raised
  Teal deep       #CCFBF1    ds-bg-teal-deep
  Teal wash       #E6FAF8    ds-bg-teal-wash

Brand:
  Teal primary    #0D9488    ds-teal-primary     (unchanged)
  Teal highlight  #2DD4BF    ds-teal-highlight   (unchanged)

Text:
  Primary         #18181B    ds-text-primary
  Secondary       #6B7280    ds-text-secondary
  Tertiary        #9CA3AF    ds-text-tertiary
  Teal text       #0D9488    ds-text-teal

Borders:
  Default         #C8CDD5    ds-border-default
  Teal            #0D9488    ds-border-teal
  Subtle          #D1D5DB    ds-border-subtle

States:
  Success         #16A34A    ds-success
  Success bg      #DCFCE7    ds-success-bg
  Danger          #DC2626    ds-danger
  Danger bg       #FEE2E2    ds-danger-bg
  Warning         #D97706    ds-warning
  Warning bg      #FEF3C7    ds-warning-bg
  Pending         #9CA3AF    ds-pending
  Locked          #E4E4E7    ds-locked
```

---

## Tailwind CSS Configuration

Add the following to your `tailwind.config.js` under `theme.extend.colors`.
Both dark and light mode values are registered — Tailwind's dark mode class
strategy (`darkMode: 'class'`) handles the switch at the `:root` or `html`
level via a `dark` class toggle driven by the user's system preference or
a manual toggle stored in Pinia.

```js
// tailwind.config.js
module.exports = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        ds: {
          // Brand — same in both modes
          'teal-primary':    '#0D9488',
          'teal-highlight':  '#2DD4BF',

          // Dark mode surfaces
          'dark-bg':         '#18181B',
          'dark-surface':    '#27272A',
          'dark-raised':     '#3F3F46',
          'dark-teal-deep':  '#134E4A',
          'dark-teal-wash':  '#0D2320',

          // Light mode surfaces
          'light-bg':        '#E8ECED',
          'light-surface':   '#FFFFFF',
          'light-raised':    '#F3F5F6',
          'light-teal-deep': '#CCFBF1',
          'light-teal-wash': '#E6FAF8',

          // Dark mode text
          'dark-text-primary':   '#FAFAFA',
          'dark-text-secondary': '#A1A1AA',
          'dark-text-tertiary':  '#71717A',

          // Light mode text
          'light-text-primary':   '#18181B',
          'light-text-secondary': '#6B7280',
          'light-text-tertiary':  '#9CA3AF',

          // Dark mode borders
          'dark-border':        '#3F3F46',
          'dark-border-subtle': '#27272A',

          // Light mode borders
          'light-border':        '#C8CDD5',
          'light-border-subtle': '#D1D5DB',

          // Shared teal border
          'border-teal': '#0D9488',

          // Semantic — dark mode
          'dark-success':     '#22C55E',
          'dark-success-bg':  '#052E16',
          'dark-danger':      '#EF4444',
          'dark-danger-bg':   '#3F0A0A',
          'dark-warning':     '#F59E0B',
          'dark-warning-bg':  '#2D1A00',

          // Semantic — light mode
          'light-success':    '#16A34A',
          'light-success-bg': '#DCFCE7',
          'light-danger':     '#DC2626',
          'light-danger-bg':  '#FEE2E2',
          'light-warning':    '#D97706',
          'light-warning-bg': '#FEF3C7',

          // Shared neutral states
          'pending': '#A1A1AA',
        }
      }
    }
  }
}
```

---

## CSS Custom Properties (Optional Alternative)

If you prefer CSS variables over Tailwind tokens — or want to use both —
define the following in your `app.css` or a dedicated `variables.css` file.
The `:root` block covers light mode; the `.dark` block overrides for dark mode.

```css
/* variables.css */

:root {
  /* Surfaces */
  --ds-bg-primary:        #E8ECED;
  --ds-bg-surface:        #FFFFFF;
  --ds-bg-surface-raised: #F3F5F6;
  --ds-bg-teal-deep:      #CCFBF1;
  --ds-bg-teal-wash:      #E6FAF8;

  /* Brand */
  --ds-teal-primary:      #0D9488;
  --ds-teal-highlight:    #2DD4BF;

  /* Text */
  --ds-text-primary:      #18181B;
  --ds-text-secondary:    #6B7280;
  --ds-text-tertiary:     #9CA3AF;
  --ds-text-teal:         #0D9488;

  /* Borders */
  --ds-border-default:    #C8CDD5;
  --ds-border-teal:       #0D9488;
  --ds-border-subtle:     #D1D5DB;

  /* States */
  --ds-success:           #16A34A;
  --ds-success-bg:        #DCFCE7;
  --ds-danger:            #DC2626;
  --ds-danger-bg:         #FEE2E2;
  --ds-warning:           #D97706;
  --ds-warning-bg:        #FEF3C7;
  --ds-pending:           #9CA3AF;
  --ds-locked:            #E4E4E7;
}

.dark {
  /* Surfaces */
  --ds-bg-primary:        #18181B;
  --ds-bg-surface:        #27272A;
  --ds-bg-surface-raised: #3F3F46;
  --ds-bg-teal-deep:      #134E4A;
  --ds-bg-teal-wash:      #0D2320;

  /* Brand — unchanged */
  --ds-teal-primary:      #0D9488;
  --ds-teal-highlight:    #2DD4BF;

  /* Text */
  --ds-text-primary:      #FAFAFA;
  --ds-text-secondary:    #A1A1AA;
  --ds-text-tertiary:     #71717A;
  --ds-text-teal:         #2DD4BF;

  /* Borders */
  --ds-border-default:    #3F3F46;
  --ds-border-teal:       #0D9488;
  --ds-border-subtle:     #27272A;

  /* States */
  --ds-success:           #22C55E;
  --ds-success-bg:        #052E16;
  --ds-danger:            #EF4444;
  --ds-danger-bg:         #3F0A0A;
  --ds-warning:           #F59E0B;
  --ds-warning-bg:        #2D1A00;
  --ds-pending:           #A1A1AA;
  --ds-locked:            #3F3F46;
}
```

---

## Semantic Usage Guide

This section documents how each color role should be applied consistently
across all components and views.

### Odds Display

```
Drafted odds (historical reference)   ds-text-secondary  — muted, not prominent
Current odds (live updated)           ds-text-teal       — teal accent, draws eye
Favorable drift (odds shortened)      ds-success         — green, positive signal
Unfavorable drift (odds lengthened)   ds-danger          — red, negative signal
Odds unchanged                        ds-text-secondary  — no color signal needed
```

### Pick / Slate Slot States

```
Starter slot — active, unlocked       ds-bg-teal-deep border ds-border-teal
Starter slot — locked (pre-result)    ds-locked bg, ds-text-secondary text
Starter slot — HIT                    ds-success-bg bg, ds-success text/icon
Starter slot — MISS                   ds-danger-bg bg, ds-danger text/icon
Starter slot — PENDING                ds-bg-surface, ds-pending indicator
Bench slot — unlocked                 ds-bg-surface, no teal accent
Bench slot — locked                   ds-locked bg, ds-text-tertiary text
Empty slot                            ds-bg-surface-raised, dashed ds-border-default
```

### Matchup Score Display

```
User winning                          ds-success — green score value
User losing                           ds-danger — red score value
Tied                                  ds-text-primary — neutral
Final WIN banner                      ds-success-bg bg, ds-success text
Final LOSS banner                     ds-danger-bg bg, ds-danger text
Final TIE banner                      ds-bg-surface-raised, ds-text-secondary
```

### Draft Interface

```
Available pick — eligible             ds-bg-surface, ds-border-default
Available pick — selected/hover       ds-bg-teal-deep, ds-border-teal
Available pick — ineligible (odds)    ds-bg-surface-raised, ds-text-tertiary, muted
Current drafter highlight             ds-teal-primary left border accent
Draft timer — healthy (>15s)          ds-teal-primary
Draft timer — warning (6–15s)         ds-warning
Draft timer — critical (<6s)          ds-danger
Auto-pick notification                ds-warning-bg bg, ds-warning text
```

### Navigation

```
Active tab / nav item                 ds-teal-primary icon, ds-text-teal label
Inactive tab / nav item               ds-bg-surface-raised icon, ds-text-secondary label
Nav bar background                    ds-bg-primary (blends with app bg)
Nav bar top border                    ds-border-default
```

### Badges & Pills

```
STARTER badge                         ds-bg-teal-deep bg, ds-text-teal text
BENCH badge                           ds-bg-surface-raised, ds-text-secondary
LOCKED badge                          ds-locked bg, ds-text-tertiary
HIT badge                             ds-success-bg, ds-success
MISS badge                            ds-danger-bg, ds-danger
PENDING badge                         ds-bg-surface-raised, ds-pending
Draft tonight badge                   ds-teal-primary bg, white text
Manage slate badge                    ds-locked bg, ds-text-secondary
WIN badge (matchup result)            ds-success-bg, ds-success
LOSS badge (matchup result)           ds-danger-bg, ds-danger
Championship badge                    #D4AF37 gold — one-off, career profile only
```

---

## Typography Pairing Recommendation

The color palette pairs best with a geometric sans-serif for UI chrome and
a slightly more characterful display face for headings and the wordmark.
Suggested pairing for consideration:

```
Display / Wordmark:   DM Sans (700) or Sora (700)
UI / Body:            DM Sans (400, 500) or Inter (400, 500)
Monospace (odds):     JetBrains Mono or Fira Code — odds values displayed
                      in a monospace face adds a data/terminal quality that
                      reinforces the analytical brand identity
```

All available via Google Fonts at no cost.

---

*DraftSlate Color Palette Reference — v1.0*
*To be incorporated into the DraftSlate technical architecture .md document*

---
name: Stadium Energy
colors:
  surface: '#f9f9f9'
  surface-dim: '#dadada'
  surface-bright: '#f9f9f9'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f3f3'
  surface-container: '#eeeeee'
  surface-container-high: '#e8e8e8'
  surface-container-highest: '#e2e2e2'
  on-surface: '#1a1c1c'
  on-surface-variant: '#454932'
  inverse-surface: '#2f3131'
  inverse-on-surface: '#f1f1f1'
  outline: '#767960'
  outline-variant: '#c6c9ab'
  surface-tint: '#576500'
  primary: '#576500'
  on-primary: '#ffffff'
  primary-container: '#dfff00'
  on-primary-container: '#647400'
  inverse-primary: '#b8d300'
  secondary: '#5f5e5e'
  on-secondary: '#ffffff'
  secondary-container: '#e2dfde'
  on-secondary-container: '#636262'
  tertiary: '#5d5f5f'
  on-tertiary: '#ffffff'
  tertiary-container: '#f0f0f0'
  on-tertiary-container: '#6c6d6d'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d2f000'
  primary-fixed-dim: '#b8d300'
  on-primary-fixed: '#191e00'
  on-primary-fixed-variant: '#414c00'
  secondary-fixed: '#e5e2e1'
  secondary-fixed-dim: '#c8c6c5'
  on-secondary-fixed: '#1c1b1b'
  on-secondary-fixed-variant: '#474746'
  tertiary-fixed: '#e2e2e2'
  tertiary-fixed-dim: '#c6c6c6'
  on-tertiary-fixed: '#1a1c1c'
  on-tertiary-fixed-variant: '#454747'
  background: '#f9f9f9'
  on-background: '#1a1c1c'
  surface-variant: '#e2e2e2'
typography:
  display-lg:
    fontFamily: Sora
    fontSize: 64px
    fontWeight: '800'
    lineHeight: '1.1'
    letterSpacing: -0.04em
  display-lg-mobile:
    fontFamily: Sora
    fontSize: 40px
    fontWeight: '800'
    lineHeight: '1.1'
    letterSpacing: -0.03em
  headline-lg:
    fontFamily: Sora
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Sora
    fontSize: 24px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
    letterSpacing: -0.01em
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
    letterSpacing: '0'
  label-bold:
    fontFamily: Sora
    fontSize: 14px
    fontWeight: '700'
    lineHeight: '1'
    letterSpacing: 0.05em
  caption:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: '1.4'
    letterSpacing: '0'
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  container-margin: 24px
  gutter: 16px
  section-padding: 80px
---

## Brand & Style

The design system is engineered for **Stadium Energy**—a high-performance aesthetic that mirrors the intensity of a match-day under floodlights. It targets the elite athlete and football enthusiast who values expertise and precision. The visual language is clean and focused, eliminating unnecessary clutter to highlight product technology and speed.

The style is a fusion of **High-Contrast Boldness** and **Modern Minimalism**. Large, impactful typography and a vibrant accent color provide the "energy," while generous off-white whitespace and refined geometry maintain a "premium" positioning. The emotional response should be one of readiness, speed, and professional-grade reliability.

## Colors

The palette is designed to create a high-contrast, high-visibility environment. 

*   **Electric Green (#DFFF00):** Used exclusively for calls-to-action, active states, and critical performance highlights. It represents the "spark" on the pitch.
*   **Deep Charcoal (#1A1A1A):** Provides the structural weight. Used for primary headlines, body text, and dark-mode-style containers to ensure maximum legibility and an "expert" feel.
*   **Soft Neutrals (#F5F5F5 & #E5E5E5):** These off-whites and warm grays form the "stadium floor," providing a clean, sophisticated alternative to pure white that reduces glare and adds a premium touch.

## Typography

The typography strategy leverages **Sora** for high-impact display moments and **Inter** for functional readability. 

**Headlines** must use Sora with tight tracking (negative letter spacing) and heavy weights to convey a sense of "speed" and "density." For marketing sections, use the `display-lg` style to dominate the viewport.

**Body Text** utilizes Inter for its utilitarian, neutral character. This ensures that technical product descriptions are easy to digest. **Labels** return to Sora in an uppercase format to provide a technical, "spec-sheet" feel to product attributes like weight, stud type, and material.

## Layout & Spacing

This design system utilizes a **Fixed Grid** model for desktop to maintain a premium, editorial feel, and a **Fluid Grid** for mobile to maximize thumb-space.

*   **Desktop:** 12-column grid, 1200px max-width, 24px gutters. Content should feel centered and "heroic."
*   **Mobile:** 4-column grid with 16px margins. 
*   **Rhythm:** Use an 8px baseline power-of-two scale. Section vertical padding is intentionally generous (80px+) to allow the high-impact photography of boots and athletes to "breathe," reinforcing the premium positioning.

## Elevation & Depth

Visual hierarchy is achieved through **Tonal Layering** and **Subtle Soft Shadows**. 

Avoid heavy dropshadows. Instead, use "ambient" shadows—low-opacity (4-8%), large blur radius (20px+), and slightly tinted with the Deep Charcoal color. This makes cards appear as if they are floating just above the turf.

Higher elevation tiers (like modals or floating cart buttons) should use a crisp 1px border of `#E5E5E5` in addition to the shadow to maintain a sharp, technical edge. Background blurs may be used sparingly on navigation bars to maintain a sense of space as the user scrolls.

## Shapes

The shape language is characterized by **Rounded-XL** geometry. While the brand is "aggressive," the use of 16px+ corner radii on cards and buttons creates a modern, "lifestyle-meets-performance" aesthetic.

*   **Primary Containers:** 24px (rounded-xl) for product cards and main containers.
*   **Buttons & Inputs:** 12px-16px to ensure a comfortable, tactile feel.
*   **Small Elements:** 8px for chips and tooltips.

The consistency of these soft corners against the sharp, high-contrast typography creates the "Premium Athletic" tension that defines the brand.

## Components

### Buttons
*   **Primary:** Background `#DFFF00`, Text `#1A1A1A`, Weight `700`, Rounded `16px`. On hover, apply a slight upward lift (2px) and deepen the shadow.
*   **Secondary:** Background `Transparent`, Border 2px `#1A1A1A`, Text `#1A1A1A`.

### Cards
*   **Product Cards:** Background `#FFFFFF`, rounded `24px`, subtle ambient shadow. Images should be isolated (PNG) with a soft shadow beneath the boot to ground it.

### Input Fields
*   **Styling:** Background `#F5F5F5`, Border 1px `#E5E5E5`, focus state border `#1A1A1A`. No glow—keep focus states sharp and mechanical.

### Chips & Badges
*   **Performance Tags:** Use `#1A1A1A` background with `#DFFF00` text for "New Arrival" or "Elite" tags. Keep them small, uppercase, and bold.

### Lists
*   **Technical Specs:** Use horizontal dividers in `#E5E5E5`. Icons should be 20px, stroke-based (2px weight), and sharp.

### Specialized Components
*   **The "Speed Gauge":** A visual indicator for boot weight or traction levels, using a horizontal bar that fills with Electric Green to represent high-performance metrics.
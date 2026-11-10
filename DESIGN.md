---
version: alpha
name: OfferPilot
description: Quiet commerce for negotiated retail. Ink and paper, one teal accent, no decoration.
colors:
    primary: 'oklch(0.205 0 0)'
    primary-foreground: 'oklch(0.985 0 0)'
    accent: 'oklch(0.52 0.11 175)'
    accent-strong: 'oklch(0.48 0.12 175)'
    accent-inverse: 'oklch(0.72 0.10 175)'
    on-accent: 'oklch(1 0 0)'
    background: 'oklch(1 0 0)'
    surface: 'oklch(0.97 0 0)'
    foreground: 'oklch(0.145 0 0)'
    muted-foreground: 'oklch(0.54 0 0)'
    border: 'oklch(0.922 0 0)'
    destructive: 'oklch(0.577 0.245 27.325)'
typography:
    headline-lg:
        fontFamily: Instrument Sans
        fontSize: 30px
        fontWeight: 600
        lineHeight: 1.15
        letterSpacing: -0.01em
    headline-md:
        fontFamily: Instrument Sans
        fontSize: 24px
        fontWeight: 600
        lineHeight: 1.2
    body-md:
        fontFamily: Instrument Sans
        fontSize: 16px
        fontWeight: 400
        lineHeight: 1.6
    body-sm:
        fontFamily: Instrument Sans
        fontSize: 14px
        fontWeight: 400
        lineHeight: 1.5
    label-caps:
        fontFamily: Instrument Sans
        fontSize: 12px
        fontWeight: 500
        lineHeight: 1
        letterSpacing: 0.08em
spacing:
    xs: 4px
    sm: 8px
    md: 16px
    lg: 24px
    xl: 32px
    section: 48px
rounded:
    sm: 6px
    md: 8px
    lg: 10px
    full: 9999px
components:
    button-primary:
        backgroundColor: '{colors.accent}'
        textColor: '{colors.on-accent}'
        rounded: '{rounded.md}'
        padding: 12px
        height: 44px
    button-primary-dark:
        backgroundColor: '{colors.accent-inverse}'
        textColor: '{colors.primary}'
        rounded: '{rounded.md}'
        height: 44px
    button-secondary:
        backgroundColor: '{colors.background}'
        textColor: '{colors.foreground}'
        rounded: '{rounded.md}'
        height: 44px
    button-dark:
        backgroundColor: '{colors.primary}'
        textColor: '{colors.primary-foreground}'
        rounded: '{rounded.md}'
        height: 44px
    input:
        backgroundColor: '{colors.background}'
        textColor: '{colors.foreground}'
        rounded: '{rounded.md}'
        height: 44px
    input-error:
        backgroundColor: '{colors.background}'
        textColor: '{colors.destructive}'
        rounded: '{rounded.md}'
        height: 44px
    product-card:
        backgroundColor: '{colors.surface}'
        textColor: '{colors.muted-foreground}'
        typography: '{typography.body-sm}'
        rounded: '{rounded.lg}'
        padding: 16px
    chat-assistant:
        backgroundColor: '{colors.surface}'
        textColor: '{colors.foreground}'
        typography: '{typography.body-md}'
        rounded: '{rounded.md}'
        padding: 12px
    badge-available:
        backgroundColor: '{colors.surface}'
        textColor: '{colors.accent-strong}'
        typography: '{typography.label-caps}'
        rounded: '{rounded.sm}'
        padding: 4px
    nav-link:
        backgroundColor: '{colors.background}'
        textColor: '{colors.muted-foreground}'
        typography: '{typography.body-sm}'
        height: 44px
---

# OfferPilot

## Overview

OfferPilot is a seller's sales assistant, presented as a storefront. The buyer
arrives to shop; the seller has already configured the catalogue, the prices and
the private limits the assistant may negotiate within.

The interface should feel like **quiet commerce**: a calm retail surface where
the product and its price lead, and the transaction is unambiguous. This is not
a dashboard, not a developer demo, and not a chatbot product. Commerce first;
the assistant is staff, not the attraction.

The emotional target is **trust at the moment money moves**. Every screen should
feel like a well-run shop: factual, unhurried, and explicit about what will be
charged. Nothing should read as a prototype — no "sandbox", no "test user", no
internal vocabulary.

Two audiences, one system: the seller configures (denser, task-oriented), the
buyer shops (sparser, reassurance-oriented). Both use the same tokens.

## Colors

The palette is ink and paper with a single accent. There is no second brand
colour; restraint is the brand.

- **Ink (`primary`, `foreground`):** near-black, used for text and filled primary
  buttons. 16.9:1 on white.
- **Teal (`accent`):** the only expressive colour. Reserved for the single
  primary action per screen and for verified-payment states. 5.11:1 on white,
  and white on it is 5.11:1, so it passes AA in both directions.
- **Teal inverse (`accent-inverse`):** the dark-mode accent. 8.36:1 on ink.
- **Paper (`background`) and Surface (`surface`):** pure white and a 3% grey for
  grouping. Never tint the page background with the accent.
- **Muted (`muted-foreground`):** secondary text, 4.94:1 — passes AA, so metadata
  may use it but nothing that must be read at a glance.
- **Destructive:** reserved for genuine failure. Never used for negotiation
  outcomes; a rejected offer is information, not an error.

Accent is a signal, not decoration. If two elements on a screen are teal, one of
them is wrong.

## Typography

One family, **Instrument Sans**, already loaded by the application. Hierarchy
comes from size and weight, never from a second typeface.

- **Headlines** are semibold at 24–30px with tight tracking. The product name and
  the price are the two loudest things on a product page; the price may equal or
  exceed the name in weight.
- **Body** is 16px with 1.6 line height. Never 14px for prose: 16px also prevents
  iOS from zooming on input focus.
- **Labels** are 14px. Caps labels are 12px with 0.08em tracking, reserved for
  seller and category metadata, never for prices or actions.

Numbers are formatted for humans: currency always carries its symbol, grouping
and two decimals (`$1,299.00`). Never render a raw minor-unit integer.

## Layout

Fluid, single-column on mobile, and capped at **1024px** on desktop for the
storefront. The seller area may reach 1280px because it is tabular.

A 4px base rhythm with an 8/16/24/32 scale. Related items are grouped by
proximity and a single hairline border; avoid nested cards. On a product page the
image and the buy panel sit side by side from 768px up, and stack below it.

Primary actions sit within thumb reach on mobile: offer and pay controls belong in
the lower half of the viewport, never pinned to the top.

## Elevation & Depth

Depth is conveyed by **tonal layers and hairlines**, not shadows. Cards are white
on paper with a 1px border. Shadows appear only on floating surfaces: dialogs,
dropdowns, and toasts. A shadow on a static card is a defect.

Dark mode is a first-class theme, not an inversion: background near-black, cards
one step lighter, borders at low contrast, and the accent swapped to
`accent-inverse`.

## Shapes

Rounded corners are restrained: 8px on inputs and buttons, 10px on cards, 6px for
small chips, full round only for avatars and pills. One radius per element — never
mix a 4px border with a 12px card. The PayPal button keeps PayPal's own shape; it
is never restyled.

## Components

Every component is built from the project's existing primitives
(`components/ui/*`): `Button`, `Card`, `Input`, `Label`, `Badge`, `Alert`,
`Skeleton`, `Sonner`. New markup is not created for an existing control.

- **Primary button:** teal fill, white label, 44px minimum height, one per screen.
- **Secondary button:** paper fill, hairline border, ink label, 44px minimum.
- **Inputs:** 44px minimum, 16px text, visible label above the field, helper text
  below, error text in destructive colour with an icon — never colour alone.
- **Product card:** image with a fixed aspect ratio, seller label, product name,
  price, availability badge, and two actions at most.
- **Offer panel:** the buyer's own numbers restated at the moment of payment —
  product, seller, agreed price, currency, what happens next.
- **Chat transcript:** a live log with speaker labels, a typing state, and
  scroll-to-latest. The assistant is always identified as automated.

## Do's and Don'ts

- Do use the accent for exactly one element per screen: the primary action.
- Do keep every interactive target at least 44×44px.
- Do state the amount and currency at the moment of payment.
- Do use one primary action per screen; everything else is secondary or a link.
- Do write buyer copy in shopping language.
- Don't put internal vocabulary in front of a buyer: no "sandbox", "mandate",
  "ceiling", "capture", "quote id".
- Don't ship 14px inputs; iOS zooms and the layout breaks.
- Don't use hairline-only styling for a primary action.
- Don't apply a shadow to a static surface.
- Don't use the destructive colour for a negotiation outcome.
- Don't render raw minor units or unformatted numbers.
- Don't restyle PayPal's own button.

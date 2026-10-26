---
name: Clinical Trust Marketplace
colors:
  surface: '#f6fafd'
  surface-dim: '#d6dbdd'
  surface-bright: '#f6fafd'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f0f4f7'
  surface-container: '#eaeef1'
  surface-container-high: '#e5e9ec'
  surface-container-highest: '#dfe3e6'
  on-surface: '#171c1f'
  on-surface-variant: '#414751'
  inverse-surface: '#2c3134'
  inverse-on-surface: '#edf1f4'
  outline: '#717782'
  outline-variant: '#c1c7d3'
  surface-tint: '#0060aa'
  primary: '#004f8d'
  on-primary: '#ffffff'
  primary-container: '#1267b2'
  on-primary-container: '#d5e4ff'
  inverse-primary: '#a3c9ff'
  secondary: '#47607d'
  on-secondary: '#ffffff'
  secondary-container: '#c2dcfe'
  on-secondary-container: '#48617e'
  tertiary: '#00593e'
  on-tertiary: '#ffffff'
  tertiary-container: '#007451'
  on-tertiary-container: '#7efbc5'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d3e4ff'
  primary-fixed-dim: '#a3c9ff'
  on-primary-fixed: '#001c38'
  on-primary-fixed-variant: '#004882'
  secondary-fixed: '#d1e4ff'
  secondary-fixed-dim: '#afc9ea'
  on-secondary-fixed: '#001d35'
  on-secondary-fixed-variant: '#2f4964'
  tertiary-fixed: '#7df9c4'
  tertiary-fixed-dim: '#5fdca9'
  on-tertiary-fixed: '#002114'
  on-tertiary-fixed-variant: '#005138'
  background: '#f6fafd'
  on-background: '#171c1f'
  surface-variant: '#dfe3e6'
typography:
  display-hero:
    fontFamily: IBM Plex Sans
    fontSize: 2.75rem
    fontWeight: '700'
    lineHeight: 3.5rem
  display-hero-mobile:
    fontFamily: IBM Plex Sans
    fontSize: 2rem
    fontWeight: '700'
    lineHeight: 2.75rem
  headline-lg:
    fontFamily: IBM Plex Sans
    fontSize: 2rem
    fontWeight: '700'
    lineHeight: 2.75rem
  headline-lg-mobile:
    fontFamily: IBM Plex Sans
    fontSize: 1.5rem
    fontWeight: '600'
    lineHeight: 2.25rem
  headline-md:
    fontFamily: IBM Plex Sans
    fontSize: 1.5rem
    fontWeight: '600'
    lineHeight: 2.25rem
  headline-md-mobile:
    fontFamily: IBM Plex Sans
    fontSize: 1.25rem
    fontWeight: '600'
    lineHeight: 1.875rem
  headline-sm:
    fontFamily: IBM Plex Sans
    fontSize: 1.25rem
    fontWeight: '600'
    lineHeight: 1.875rem
  body-lg:
    fontFamily: IBM Plex Sans
    fontSize: 1.125rem
    fontWeight: '400'
    lineHeight: 1.75rem
  body-md:
    fontFamily: IBM Plex Sans
    fontSize: 1rem
    fontWeight: '400'
    lineHeight: 1.625rem
  body-sm:
    fontFamily: IBM Plex Sans
    fontSize: 0.875rem
    fontWeight: '400'
    lineHeight: 1.375rem
  label-lg:
    fontFamily: IBM Plex Sans
    fontSize: 1rem
    fontWeight: '600'
    lineHeight: 1.5rem
  label-md:
    fontFamily: IBM Plex Sans
    fontSize: 0.875rem
    fontWeight: '600'
    lineHeight: 1.25rem
  label-sm:
    fontFamily: IBM Plex Sans
    fontSize: 0.75rem
    fontWeight: '600'
    lineHeight: 1rem
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-md: 1.5rem
  gutter-lg: 2rem
  margin: 1rem
  margin-md: 1.5rem
  margin-lg: 2.5rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
---

## Brand & Style

This design system serves a mission-critical medical home healthcare marketplace operating in Egypt. The emotional mandate is unambiguous: surgical professionalism, calming reliability, unquestioned authority, and immediate clarity during medical emergencies. Patients, families, and certified clinicians engage under conditions of vulnerability and urgency; the UI must eliminate cognitive friction and exude verified clinical excellence.

### Visual Style
The design language combines **Modern Clinical Precision** with **Warm Human Accessibility**:
- **Bilingual & RTL-First:** Native right-to-left layout mechanics for Arabic as primary, seamlessly mirroring for English LTR interfaces without layout distortion or awkward visual hierarchies.
- **Airy, Sanitary Canvas:** Heavy use of tinted sterile whites and soft clinical cyans creates an open, hospital-grade digital environment that avoids stark clinical sterility through warm micro-accents.
- **Micro-elevation & Structured Containers:** Visual weight is communicated through delicate border definitions and tonal shifts rather than heavy dramatized drop shadows.
- **Certified Integrity:** Prominent verification badges, status beacons, and doctor accreditation chips are structurally integrated into core card flows to reinforce trust across Egyptian governorates.

## Colors

The palette balances clinical precision with emergency utility:

- **Primary (`#1267B2` - Clinical Medical Blue):** Central brand vehicle representing institutional credibility, doctor onboarding, primary interactive triggers, and navigational active states.
- **Secondary (`#102C46` - Deep Navy):** Provides high-contrast anchor authority for typography, major structural headers, dark-tinted interactive surfaces, and deep structural chrome.
- **Tertiary (`#18A879` - Medical Success Green):** Reserved for clinical verification badges, confirmed nurse/doctor certifications, active in-progress home visits, and successful booking state indicators.
- **Emergency / SOS Accent (`#E64255` - Emergency Red):** A non-negotiable critical alert token strictly designated for instant doctor dispatch, SOS emergency calls, cancellation alerts, and vital medical warnings.
- **Neutral Background (`#F5F9FC` - Soft Clinical Background):** An eye-soothing, non-reflective base that minimizes screen fatigue for caregivers and elderly users while providing gentle contrast against pure white (`#FFFFFF`) card surfaces.

## Typography

The typography is built around **IBM Plex Sans** (paired systematically with IBM Plex Sans Arabic for native scripts), chosen for its balanced geometric legibility, wide aperture, and exceptional technical clarity at all scale ratios. Arabic letterforms maintain strict vertical proportions to eliminate baseline clipping, ensuring medical titles, dosage instructions, and patient IDs are effortlessly decipherable.

### Font Hierarchy & Roles
- **Display & Large Headlines:** Used sparingly on landing pages, triage initiation, and immediate emergency alerts.
- **Headlines (MD & SM):** Establish structural hierarchy on service cards (e.g., Home Nursing, Elderly Care, Post-Op Care, At-Home Lab Tests).
- **Body Text:** Engineered with a generous line height (`1.625rem` on 16px body) to facilitate rapid reading for stressed family members and field doctors reading patient charts on mobile devices.
- **Labels & Micro-copy:** Crisp medium-to-semibold weights ensuring clinical verification indicators, timestamps, and GPS tracking tags maintain high contrast even under direct Mediterranean sunlight.

## Layout & Spacing

The layout employs an 8pt-based, responsive fluid grid framework calibrated for Egyptian mobile network conditions and dual-orientation smartphone usage:

### Grid Architecture
- **Mobile (< 768px):** 4-column layout with `1rem` margins and `1rem` gutters. Interactive targets maintain a minimum vertical clearance of `3rem` (48px) to accommodate urgent tapping.
- **Tablet (768px - 1024px):** 8-column layout with `1.5rem` margins and `1.5rem` gutters, optimizing split-view navigation for scheduling and dispatch consoles.
- **Desktop (> 1024px):** 12-column layout capped at a max-width of `1280px` with `2.5rem` outer canvas margins and `2rem` gutters.

### RTL Grid Flow
All column orderings, offset positions, back navigation controls, and timeline steps naturally flip their progression axis from right-to-left when rendering Arabic locales. Directional icons (arrows, chevrons, flow markers) mirror automatically, whereas clinical biometric indicators (blood pressure graphs, pulse wave curves) retain standard metric readings.

## Elevation & Depth

To maintain a clean, clinical aesthetic, this design system avoids heavy shadows, instead using subtle, ambient depth created through surgical tonal shifts and crisp border definitions:

- **Level 0 (Flat Canvas):** `#F5F9FC` background canvas across viewports.
- **Level 1 (Card & Content Containers):** Pure `#FFFFFF` surface paired with an ultra-thin border (`1px solid rgba(16, 44, 70, 0.08)`) and an ambient tinted drop shadow: `0px 2px 8px rgba(18, 103, 178, 0.04)`.
- **Level 2 (Interactive Floating Elements & Map Overlays):** Used for real-time doctor dispatch trackers, active filters, and floating booking bars: `#FFFFFF` surface with `0px 6px 20px rgba(16, 44, 70, 0.08)`.
- **Level 3 (Modals & Emergency Drawers):** SOS call sheets, confirmation sheets, and vital triage popovers: `#FFFFFF` with `0px 16px 36px rgba(16, 44, 70, 0.16)` over a tinted `#102C46` backdrop scrim at 45% opacity.

## Shapes

The shape system adopts **Level 2 (Rounded)**:
- Standard UI elements (inputs, standard buttons, badge containers) use `0.5rem` (8px).
- Clinical service cards, clinician profiles, and booking modules use `1rem` (16px, `rounded-lg`).
- Floating bottom drawers, modal containers, and emergency bottom-sheets utilize `1.5rem` (24px, `rounded-xl`).
- Pill shapes (`rounded-full`) are strictly reserved for clinician status pills (e.g., "On the Way", "Syndicate Verified"), rating pills, and emergency quick-trigger buttons.

## Components

### Buttons
- **Primary Clinical Action:** Background `#1267B2`, text `#FFFFFF`, `rounded-md` (8px). 48px height on mobile to ensure accessible touch targets. Subtle hover/pressed state darkening to `#0E528E`.
- **Emergency / SOS Action:** Prominent background `#E64255`, text `#FFFFFF`, bold weight. Houses an alert siren icon followed by emergency Arabic copy ("طلب زيارة طارئة فورية"). Features a soft pulsing red outer glow.
- **Secondary Outlined:** Border `1.5px solid #1267B2`, background transparent, text `#1267B2`.

### Clinical Verification Chips & Status Badges
- **Verified Practitioner Badge:** Background `rgba(24, 168, 121, 0.12)`, text `#18A879`, with an embedded checkmark shield icon indicating licensing by the Egyptian Ministry of Health / Doctors Syndicate.
- **Live Visit Tracker Pill:** Background `rgba(18, 103, 178, 0.1)`, text `#1267B2`, pairing an animated pulsing dot indicator with estimated arrival times (e.g., "متبقي ١٥ دقيقة").

### Cards & Service Containers
- **Doctor / Nurse Marketplace Card:** White container with `rounded-lg` (16px), 1px stroke (`rgba(16, 44, 70, 0.08)`). Displays practitioner portrait, verified syndicate credentials, specialties (ICU nurse, geriatric caregiver, physiotherapist), pricing in EGP, and real-time availability.
- **RTL-Optimized Hierarchy:** In Arabic mode, doctor metadata and rating starts from the top right; price and booking CTA occupy the bottom left.

### Form Inputs & Health Selectors
- **Input Fields:** Generous 48px height, background `#FFFFFF`, border `1px solid rgba(16, 44, 70, 0.2)`. On active focus, transitions to `#1267B2` with a 3px soft cyan focus ring (`rgba(18, 103, 178, 0.15)`).
- **RTL Floating Labels:** Micro-labels float gracefully right-to-left. Text inputs for Egyptian national IDs, phone numbers (`+20`), and medical dosages retain left-to-right numeral flow while retaining Arabic descriptors.

### Real-time Dispatch Bar & Emergency Drawer
- Pinned bottom-sheet containing the assigned field clinician's photo, direct phone link, live GPS progress bar, and vehicle/equipment readiness indicators. Designed with a physical, reassuring presence to alleviate user anxiety during wait times.
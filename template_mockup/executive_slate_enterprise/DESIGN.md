---
name: Executive Slate Enterprise
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#45464d'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#76777d'
  outline-variant: '#c6c6cd'
  surface-tint: '#565e74'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#131b2e'
  on-primary-container: '#7c839b'
  inverse-primary: '#bec6e0'
  secondary: '#904d00'
  on-secondary: '#ffffff'
  secondary-container: '#fe932c'
  on-secondary-container: '#663500'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#0d1c2f'
  on-tertiary-container: '#76859b'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dae2fd'
  primary-fixed-dim: '#bec6e0'
  on-primary-fixed: '#131b2e'
  on-primary-fixed-variant: '#3f465c'
  secondary-fixed: '#ffdcc3'
  secondary-fixed-dim: '#ffb77d'
  on-secondary-fixed: '#2f1500'
  on-secondary-fixed-variant: '#6e3900'
  tertiary-fixed: '#d5e3fd'
  tertiary-fixed-dim: '#b9c7e0'
  on-tertiary-fixed: '#0d1c2f'
  on-tertiary-fixed-variant: '#3a485c'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
  surface-canvas: '#F8FAFC'
  surface-card: '#FFFFFF'
  surface-subtle: '#F1F5F9'
  border-subtle: '#E2E8F0'
  border-strong: '#CBD5E1'
  status-available: '#059669'
  status-assigned: '#2563EB'
  status-loan: '#7C3AED'
  status-repair: '#EA580C'
  status-disposal: '#DC2626'
  status-neutral: '#475569'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  display-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.015em
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  title-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
    letterSpacing: -0.005em
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.02em
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.04em
  code-sm:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-mobile: 0.75rem
  margin: 2rem
  margin-mobile: 1rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-lg: 1.25rem
  space-xl: 2rem
---

## Brand & Style

This design system establishes an authoritative, reliable, and high-precision visual standard for enterprise asset management and governance workflows. Designed for asset custodians, procurement directors, compliance auditors, and executive decision-makers, the interface balances Material Design 3 state clarity with the structured ergonomics of administrative operating dashboards.

### Aesthetic Foundation
- **Modern Corporate Precision:** An intersection of utilitarian admin panel architecture (persistent navigation, high-density data tables, modular split-screens) with refined, executive materiality.
- **Tone & Mood:** Impartial, institutional, unshakeable. It conveys strict fiscal stewardship, operational hygiene, and cryptographic certainty. Every visual boundary reinforces auditability and clarity without distracting ornamentation.
- **Spatial Disciplines:** Clear visual hierarchies, tactile container separations, and restrained luxury accents in burnished bronze/gold indicate priority, monetary impact, and required approvals.

## Colors

The palette establishes an executive ambiance through neutral foundations of titanium slate, deep charcoal, and clean platinum surfaces, accented by warm executive bronze.

### Color Roles & Rationale
- **Primary (`#0F172A` - Executive Metallic Slate):** Anchors core actions, structural headers, high-level navigation chrome, and active table selection states.
- **Secondary (`#D97706` - Warm Executive Bronze):** Highlights monetary approvals, pending audit queues, highlighted action calls, and mission-critical milestones.
- **Tertiary (`#334155` - Titanium Gray):** Handles secondary buttons, inactive navigational headers, sorting controls, and structural grouping borders.
- **Neutral (`#64748B` - Slate Muted):** Used for micro-copy, timestamps, table pagination controls, and peripheral metadata labels.

### Semantic Domain Statuses
- **Status Available (`#059669`):** Indicates operational readiness, active allocation clearance, and finalized approvals.
- **Status Assigned (`#2563EB`):** Indicates assets deployed in active employee custody.
- **Status On Loan (`#7C3AED`):** Identifies temporary lending arrangements and time-bounded checkouts.
- **Status In Repair (`#EA580C`):** Highlights maintenance holds, active ticketing, or hardware degradation.
- **Status Disposal (`#DC2626`):** Identifies decommissioning, scrap, write-offs, or rejected requests.
- **Status Neutral (`#475569`):** Reserved for uninitialized records, drafts, and archived historical states.

## Typography

The typography uses Inter across all hierarchical levels to maximize readability across dense numerical data, tabular metrics, and enterprise audit records. A complementary monospaced font handles database keys, serial identifiers, and audit hashes.

### Typographic Disciplines
- **Numerical Alignment:** Tabular numbers (`font-variant-numeric: tabular-nums`) must be enforced for all data tables, fiscal figures, and asset tags to ensure seamless scanning across rows.
- **Letter Spacing:** Tighter tracking on display styles preserves compactness, while positive tracking on label levels ensures illegibility is avoided at sub-12px sizes.
- **Monospace Usage:** `code-sm` is explicitly reserved for immutable technical entities (`employee_id`, asset serial numbers, approval transaction UUIDs).

## Layout & Spacing

This layout architecture implements a robust 12-column administrative grid integrated with Material Design 3 container disciplines and structured sidebar conventions.

### Structural Framework
- **Primary Shell:** Fixed/collapsible 260px left sidebar navigation paired with a persistent 64px utility header. The main workspace flows within fluid constraints with a maximum content canvas of 1600px.
- **Rhythm & Increments:** Base spatial grid is built on a 4px/8px standard. Tight horizontal padding in tables (`space-sm` to `space-md`) supports data density, while card sections use `space-lg` and page bounds use `margin`.
- **Responsive Adaptations:**
  - **Desktop (≥1200px):** 12 columns, 24px gutters, dual-pane or multi-column metric widgets.
  - **Tablet (768px - 1199px):** Sidebar collapses to an icon-only rail (64px). Tables become horizontally scrollable with sticky header and action columns.
  - **Mobile (<768px):** Off-canvas drawer navigation, single-column reflow, stacked metric cards, and 12px canvas padding.

## Elevation & Depth

Visual hierarchy uses crisp, architectural low-contrast borders combined with faint, warm-slate ambient drop shadows. This preserves enterprise clarity while avoiding visually noisy 3D effects.

### Tiers of Depth
- **Level 0 (Canvas Surface):** Color `#F8FAFC`. Base canvas background on which cards and panels sit.
- **Level 1 (Default Containers & Metric Cards):** Color `#FFFFFF`, bounded by a `1px solid #E2E8F0` border and an ambient shadow: `0 1px 3px 0 rgba(15, 23, 42, 0.04), 0 1px 2px -1px rgba(15, 23, 42, 0.03)`.
- **Level 2 (Hovered Records & Floating Actions):** Color `#FFFFFF`, bounded by `1px solid #CBD5E1` with shadow: `0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 2px 4px -2px rgba(15, 23, 42, 0.04)`.
- **Level 3 (Dropdown Menus, Popovers, & Date Pickers):** Color `#FFFFFF`, bounded by `1px solid #CBD5E1` with shadow: `0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -4px rgba(15, 23, 42, 0.04)`.
- **Level 4 (Modal Dialogs & Approval Drawers):** Elevated surface overlay with an opaque backdrop of `#0F172A` at 40% opacity, paired with shadow: `0 20px 25px -5px rgba(15, 23, 42, 0.12), 0 8px 10px -6px rgba(15, 23, 42, 0.06)`.

## Shapes

The design system adopts a crisp, professional shape language (`roundedness: 1`). Structural controls and enterprise cards balance ergonomic soft geometry with the clean discipline of traditional enterprise forms.

### Geometry Specifications
- **Controls & Buttons:** 4px radius (`0.25rem`). Maintains strong architectural edges suitable for data-heavy layouts.
- **Containers & Tables:** 8px outer corner radius (`0.5rem`). Interior cells retain 0px to preserve continuous grid lines.
- **Status Pills & Micro Badges:** Fully rounded capsule/pill (`9999px`) to create an immediate visual contrast against geometric card corners and tabular rows.

## Components

### Buttons
- **Primary:** Background `#0F172A`, text `#FFFFFF`, 4px radius, 36px standard height (`space-sm` vertical, `space-md` horizontal padding). Transitions to `#1E293B` on hover with crisp focus ring: `2px solid #D97706` with 2px offset.
- **Secondary / Action:** Surface `#FFFFFF`, border `1px solid #CBD5E1`, text `#334155`. Hover background `#F1F5F9`.
- **Accent (Approval / High-Tier):** Background `#D97706`, text `#FFFFFF`, hover `#B45309`. Used for final procurement authorizations and asset sign-offs.

### Data Tables
- **Header:** Background `#F8FAFC`, height 40px, text uppercase `label-md` in `#475569`, border-bottom `2px solid #CBD5E1`.
- **Rows:** Minimum height 48px, background `#FFFFFF`, border-bottom `1px solid #E2E8F0`. Hover state triggers background `#F1F5F9` at 50% opacity.
- **Cells:** Padding 8px 16px, aligned using `body-sm`. Numeric fields and identifiers strictly right-aligned or monospaced.

### Status Pills
- **Geometry:** Height 22px, padding 2px 8px, font `label-sm`, rounded-pill (`9999px`).
- **Colorways:** Light-tinted fill with solid text:
  - *Available:* Background `#ECFDF5`, text `#065F46`, border `1px solid #A7F3D0`.
  - *Assigned:* Background `#EFF6FF`, text `#1E40AF`, border `1px solid #BFDBFE`.
  - *On Loan:* Background `#F5F3FF`, text `#5B21B6`, border `1px solid #DDD6FE`.
  - *In Repair:* Background `#FFF7ED`, text `#9A3412`, border `1px solid #FED7AA`.
  - *Disposed / Rejected:* Background `#FEF2F2`, text `#991B1B`, border `1px solid #FECACA`.

### Metric Summary Cards
- White background (`#FFFFFF`), border `1px solid #E2E8F0`, padding `space-lg`.
- Top: Category label in `label-md` (`#64748B`) paired with a contextual icon.
- Middle: Large value in `display-md` (`#0F172A`).
- Bottom: Contextual delta badge indicating percentage shifts or SLA status indicators.

### Form Inputs & Selects
- Height 38px, background `#FFFFFF`, border `1px solid #CBD5E1`, font `body-md` (`#0F172A`).
- Focus state: border-color `#0F172A`, focus outline `2px solid #D97706` at 20% alpha.
- Disabled state: background `#F1F5F9`, border-color `#E2E8F0`, cursor not-allowed.

### Checkboxes & Selection Controls
- Checkbox size 16x16px, 3px border radius. Unchecked border `1.5px solid #64748B`.
- Checked state: background `#0F172A`, checkmark icon in `#FFFFFF`.
- Indeterminate state: background `#334155` with centered white horizontal bar.

### Admin Navigation Sidebar
- Background `#0F172A`, text `#94A3B8`. Section headers in `label-sm` with `#64748B`.
- Nav Items: 40px height, rounded-sm (`4px`), margin 2px 8px, hover background `#1E293B` with text `#FFFFFF`.
- Active Item: Background `#1E293B`, text `#FFFFFF`, left accent border indicator `3px solid #D97706`.